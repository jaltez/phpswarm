<?php

declare(strict_types=1);

namespace PhpSwarm\Security;

use PhpSwarm\Contract\Logger\LoggerInterface;
use PhpSwarm\Contract\Security\SecurityManagerInterface;
use PhpSwarm\Exception\Security\SecurityException;

/**
 * Security manager implementation for PHPSwarm.
 */
class SecurityManager implements SecurityManagerInterface
{
    /**
     * @var array<string, mixed> Security settings
     */
    private array $settings;

    /**
     * @var LoggerInterface|null Logger for security events
     */
    private ?LoggerInterface $logger;

    /**
     * @var array<string> Allowed paths for file operations
     */
    private array $allowedPaths = [];

    /**
     * @var array<string> Disallowed paths for file operations
     */
    private array $disallowedPaths = [];

    /**
     * @var array<string> Allowed command prefixes
     */
    private array $allowedCommands = [];

    /**
     * @var array<string> Blocked command patterns
     */
    private array $blockedCommands = [];

    /**
     * @var array<string> Prompt injection patterns to detect
     */
    private array $injectionPatterns = [];

    /**
     * Create a new SecurityManager instance.
     *
     * @param array<string, mixed> $settings The security settings
     * @param LoggerInterface|null $logger Optional logger for security events
     */
    public function __construct(array $settings = [], ?LoggerInterface $logger = null)
    {
        $defaultSettings = [
            'validate_inputs' => true,
            'sanitize_inputs' => true,
            'check_prompt_injection' => true,
            'check_path_safety' => true,
            'check_command_safety' => true,
            'log_security_events' => true,
            'max_input_length' => 1000000, // 1MB
            'allowed_paths' => [],
            'disallowed_paths' => ['/etc', '/var/www', '/home'],
            'allowed_commands' => ['ls', 'cat', 'grep', 'find', 'echo', 'php'],
            'blocked_commands' => ['rm', 'mv', 'cp', 'chmod', 'chown', 'wget', 'curl', 'ssh', 'sudo', 'su'],
            'secure_mode' => 'standard', // 'permissive', 'standard', 'strict'
        ];

        $this->settings = array_merge($defaultSettings, $settings);
        $this->logger = $logger;

        $this->allowedPaths = $this->settings['allowed_paths'];
        $this->disallowedPaths = $this->settings['disallowed_paths'];
        $this->allowedCommands = $this->settings['allowed_commands'];
        $this->blockedCommands = $this->settings['blocked_commands'];

        // Initialize prompt injection patterns
        $this->initializeInjectionPatterns();
    }

    /**
     * {@inheritdoc}
     */
    public function validateInput(string $input, array $context = []): bool
    {
        if (!$this->settings['validate_inputs']) {
            return true;
        }

        // Check input length
        if (strlen($input) > $this->settings['max_input_length']) {
            $this->logSecurityEvent(
                'Input exceeds maximum allowed length',
                'warning',
                [
                    'input_length' => strlen($input),
                    'max_length' => $this->settings['max_input_length'],
                    'context' => $context,
                ]
            );
            return false;
        }

        // Check for potentially malicious patterns
        $maliciousPatterns = [
            '/(\<\?php|\<\?)/i', // PHP code
            '/(\<script)/i', // JavaScript
            '/(\$_GET|\$_POST|\$_REQUEST|\$_SERVER|\$_FILES)/i', // PHP globals
            '/(`|exec\s*\(|system\s*\(|passthru\s*\(|shell_exec\s*\(|' .
            'popen\s*\(|proc_open\s*\(|pcntl_exec\s*\()/i', // Command execution
            '/(file\s*\(|file_get_contents\s*\(|file_put_contents\s*\(|fopen\s*\(|readfile\s*\()/i', // File operations
            '/(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE)\s+.*\s+(FROM|INTO|TABLE|DATABASE)/i', // SQL queries
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSecurityEvent(
                    'Potentially malicious pattern detected in input',
                    'warning',
                    [
                        'pattern' => $pattern,
                        'input_preview' => substr($input, 0, 100) . (strlen($input) > 100 ? '...' : ''),
                        'context' => $context,
                    ]
                );
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isPathSafe(string $path, string $operation, array $context = []): bool
    {
        if (!$this->settings['check_path_safety']) {
            return true;
        }

        $realPath = realpath($path) ?: $path;

        // Check for directory traversal attempts
        if (strpos($path, '../') !== false || strpos($path, '..\\') !== false) {
            $this->logSecurityEvent(
                'Directory traversal attempt detected',
                'warning',
                [
                    'path' => $path,
                    'operation' => $operation,
                    'context' => $context,
                ]
            );
            return false;
        }

        // Check if the path is in the allowed list
        if (!empty($this->allowedPaths)) {
            $allowed = false;

            foreach ($this->allowedPaths as $allowedPath) {
                if (strpos($realPath, $allowedPath) === 0) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                $this->logSecurityEvent(
                    'Path not in allowed list',
                    'warning',
                    [
                        'path' => $path,
                        'real_path' => $realPath,
                        'operation' => $operation,
                        'context' => $context,
                    ]
                );
                return false;
            }
        }

        // Check if the path is in the disallowed list
        foreach ($this->disallowedPaths as $disallowedPath) {
            if (strpos($realPath, $disallowedPath) === 0) {
                $this->logSecurityEvent(
                    'Path in disallowed list',
                    'warning',
                    [
                        'path' => $path,
                        'real_path' => $realPath,
                        'disallowed_path' => $disallowedPath,
                        'operation' => $operation,
                        'context' => $context,
                    ]
                );
                return false;
            }
        }

        // Additional checks based on operation
        switch ($operation) {
            case 'read':
                if (!is_readable($realPath)) {
                    $this->logSecurityEvent(
                        'Path not readable',
                        'info',
                        [
                            'path' => $path,
                            'real_path' => $realPath,
                            'operation' => $operation,
                            'context' => $context,
                        ]
                    );
                    return false;
                }
                break;

            case 'write':
                // Check parent directory for writability
                $parentDir = dirname($realPath);
                if (!is_writable($parentDir)) {
                    $this->logSecurityEvent(
                        'Parent directory not writable',
                        'info',
                        [
                            'path' => $path,
                            'real_path' => $realPath,
                            'parent_dir' => $parentDir,
                            'operation' => $operation,
                            'context' => $context,
                        ]
                    );
                    return false;
                }
                break;

            case 'execute':
                if (file_exists($realPath) && !is_executable($realPath)) {
                    $this->logSecurityEvent(
                        'File not executable',
                        'info',
                        [
                            'path' => $path,
                            'real_path' => $realPath,
                            'operation' => $operation,
                            'context' => $context,
                        ]
                    );
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function sanitizeInput(string $input, array $context = []): string
    {
        if (!$this->settings['sanitize_inputs']) {
            return $input;
        }

        // Remove PHP tags
        $sanitized = preg_replace('/\<\?php|\<\?|\?\>/i', '', $input);

        // Remove HTML/JS tags in appropriate contexts
        if (isset($context['context']) && $context['context'] === 'html') {
            $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        }

        // Escape shell commands in appropriate contexts
        if (isset($context['context']) && $context['context'] === 'shell') {
            $sanitized = escapeshellarg($sanitized);
        }

        return $sanitized;
    }

    /**
     * {@inheritdoc}
     */
    public function detectPromptInjection(string $prompt, array $context = []): bool
    {
        if (!$this->settings['check_prompt_injection']) {
            return true;
        }

        $lowerPrompt = strtolower($prompt);

        foreach ($this->injectionPatterns as $pattern) {
            if (strpos($lowerPrompt, $pattern) !== false) {
                $this->logSecurityEvent(
                    'Potential prompt injection detected',
                    'warning',
                    [
                        'pattern' => $pattern,
                        'prompt_preview' => substr($prompt, 0, 100) . (strlen($prompt) > 100 ? '...' : ''),
                        'context' => $context,
                    ]
                );
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isCommandSafe(string $command, array $context = []): bool
    {
        if (!$this->settings['check_command_safety']) {
            return true;
        }

        // Extract the command without arguments
        $commandParts = explode(' ', trim($command));
        $baseCommand = trim($commandParts[0]);

        // Check if the command is in the blocked list
        foreach ($this->blockedCommands as $blockedCmd) {
            if ($baseCommand === $blockedCmd) {
                $this->logSecurityEvent(
                    'Blocked command execution attempt',
                    'warning',
                    [
                        'command' => $command,
                        'base_command' => $baseCommand,
                        'context' => $context,
                    ]
                );
                return false;
            }
        }

        // If we have an allowed list, check if the command is permitted
        if (!empty($this->allowedCommands)) {
            $allowed = false;

            foreach ($this->allowedCommands as $allowedCmd) {
                if ($baseCommand === $allowedCmd) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                $this->logSecurityEvent(
                    'Command not in allowed list',
                    'warning',
                    [
                        'command' => $command,
                        'base_command' => $baseCommand,
                        'context' => $context,
                    ]
                );
                return false;
            }
        }

        // Check for dangerous patterns (pipe to another command, etc.)
        $dangerousPatterns = [
            '/\s*\|\s*/', // Pipe
            '/\s*>\s*/', // Redirect output
            '/\s*>>\s*/', // Append output
            '/\s*<\s*/', // Input redirection
            '/\s*&&\s*/', // AND operator
            '/\s*\|\|\s*/', // OR operator
            '/\s*;\s*/', // Command separator
            '/`.*`/', // Backticks
            '/\$\(.*\)/', // Command substitution
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                $this->logSecurityEvent(
                    'Potentially dangerous command pattern detected',
                    'warning',
                    [
                        'command' => $command,
                        'pattern' => $pattern,
                        'context' => $context,
                    ]
                );
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function logSecurityEvent(string $event, string $level, array $context = []): void
    {
        if ($this->logger && $this->settings['log_security_events']) {
            switch ($level) {
                case 'info':
                    $this->logger->info("[SECURITY] $event", $context);
                    break;

                case 'warning':
                    $this->logger->warning("[SECURITY] $event", $context);
                    break;

                case 'error':
                    $this->logger->error("[SECURITY] $event", $context);
                    break;

                case 'critical':
                    $this->logger->critical("[SECURITY] $event", $context);
                    break;

                default:
                    $this->logger->info("[SECURITY] $event", $context);
                    break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSecuritySettings(): array
    {
        return $this->settings;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);

        // Update internal properties
        if (isset($settings['allowed_paths'])) {
            $this->allowedPaths = $settings['allowed_paths'];
        }

        if (isset($settings['disallowed_paths'])) {
            $this->disallowedPaths = $settings['disallowed_paths'];
        }

        if (isset($settings['allowed_commands'])) {
            $this->allowedCommands = $settings['allowed_commands'];
        }

        if (isset($settings['blocked_commands'])) {
            $this->blockedCommands = $settings['blocked_commands'];
        }
    }

    /**
     * Add a safe path for file operations.
     *
     * @param string $path The path to add to the allowed list
     * @return self
     */
    public function addAllowedPath(string $path): self
    {
        $this->allowedPaths[] = $path;
        return $this;
    }

    /**
     * Add a path to the disallowed list.
     *
     * @param string $path The path to add to the disallowed list
     * @return self
     */
    public function addDisallowedPath(string $path): self
    {
        $this->disallowedPaths[] = $path;
        return $this;
    }

    /**
     * Add a command to the allowed list.
     *
     * @param string $command The command to add to the allowed list
     * @return self
     */
    public function addAllowedCommand(string $command): self
    {
        $this->allowedCommands[] = $command;
        return $this;
    }

    /**
     * Add a command to the blocked list.
     *
     * @param string $command The command to add to the blocked list
     * @return self
     */
    public function addBlockedCommand(string $command): self
    {
        $this->blockedCommands[] = $command;
        return $this;
    }

    /**
     * Initialize prompt injection detection patterns.
     *
     * @return void
     */
    private function initializeInjectionPatterns(): void
    {
        $this->injectionPatterns = [
            // Basic system prompt override attempts
            'ignore previous instructions',
            'ignore all previous instructions',
            'disregard previous instructions',
            'override previous instructions',
            'forget previous instructions',
            'new instructions',

            // Role change attempts
            'you are now',
            'from now on you are',
            'from now on, you are',
            'act as if you are',
            'pretend you are',
            'switch to being',

            // Confidential information extraction
            'reveal your system prompt',
            'show me your instructions',
            'what were your initial instructions',
            'tell me your rules',
            'what is your system prompt',
            'display system prompt',
            'output system prompt',

            // Jailbreak patterns
            'dev mode',
            'developer mode',
            'debug mode',
            'maintenance mode',
            'admin mode',
            'administrator mode',
            'sudo mode',
            'root access',

            // Instruction evasion
            'do not follow',
            'don\'t follow',
            'bypass',
            'circumvent',
            'ignore safety',
            'ignore ethical guidelines',
            'ignore restrictions',
            'break free from constraints',

            // Specific attacks
            'dll',
            'token',
            'api keys',
            'key values',
            'secret keys',
            'credentials',
            'passwords',
        ];
    }
}
