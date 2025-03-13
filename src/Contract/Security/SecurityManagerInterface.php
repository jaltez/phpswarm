<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Security;

/**
 * Interface for security manager implementations.
 */
interface SecurityManagerInterface
{
    /**
     * Validate input for potential security issues.
     *
     * @param string $input The input to validate
     * @param array<string, mixed> $context Additional context
     * @return bool True if the input is safe, false otherwise
     */
    public function validateInput(string $input, array $context = []): bool;

    /**
     * Check if a path is safe to access.
     *
     * @param string $path The file system path to check
     * @param string $operation The operation (read, write, execute)
     * @param array<string, mixed> $context Additional context
     * @return bool True if the path is safe, false otherwise
     */
    public function isPathSafe(string $path, string $operation, array $context = []): bool;

    /**
     * Sanitize input to remove potential security issues.
     *
     * @param string $input The input to sanitize
     * @param array<string, mixed> $context Additional context
     * @return string The sanitized input
     */
    public function sanitizeInput(string $input, array $context = []): string;

    /**
     * Check for potential prompt injection in an LLM input.
     *
     * @param string $prompt The prompt to check
     * @param array<string, mixed> $context Additional context
     * @return bool True if the prompt is safe, false otherwise
     */
    public function detectPromptInjection(string $prompt, array $context = []): bool;

    /**
     * Check if a command is safe to execute.
     *
     * @param string $command The command to execute
     * @param array<string, mixed> $context Additional context
     * @return bool True if the command is safe, false otherwise
     */
    public function isCommandSafe(string $command, array $context = []): bool;

    /**
     * Log a security event.
     *
     * @param string $event The security event description
     * @param string $level The severity level (info, warning, error, critical)
     * @param array<string, mixed> $context Additional context
     */
    public function logSecurityEvent(string $event, string $level, array $context = []): void;

    /**
     * Get the current security settings.
     *
     * @return array<string, mixed> The security settings
     */
    public function getSecuritySettings(): array;

    /**
     * Configure security settings.
     *
     * @param array<string, mixed> $settings The security settings
     */
    public function configure(array $settings): void;
}
