<?php

declare(strict_types=1);

namespace PhpSwarm\Config;

use Dotenv\Dotenv;
use PhpSwarm\Exception\PhpSwarmException;

/**
 * Configuration helper for PHPSwarm.
 */
class PhpSwarmConfig
{
    /**
     * @var array<string, mixed> The configuration values
     */
    private array $config = [];

    /**
     * @var string|null Path to the configuration file
     */
    private ?string $configPath = null;

    /**
     * @var PhpSwarmConfig|null Singleton instance
     */
    private static ?PhpSwarmConfig $instance = null;

    /**
     * @var array<string, string> Default configuration values
     */
    private array $defaults = [
        'llm.provider' => 'openai',
        'llm.model' => 'gpt-4',
        'llm.temperature' => '0.7',
        'llm.max_tokens' => '2048',
        'memory.provider' => 'array',
        'memory.ttl' => '3600',
        'agent.verbose' => 'false',
        'agent.max_iterations' => '10',
        'agent.delegation' => 'false',
        'log.level' => 'error',
        'log.path' => 'logs',
    ];

    /**
     * Private constructor for singleton pattern.
     */
    private function __construct()
    {
        // Initialize with defaults
        foreach ($this->defaults as $key => $value) {
            $this->config[$key] = $value;
        }

        // Override from environment variables if available
        $this->loadFromEnvironment();

        // Attempt to load from .env file if it exists in the project root
        $this->loadDotEnv();
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): PhpSwarmConfig
    {
        if (!self::$instance instanceof \PhpSwarm\Config\PhpSwarmConfig) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load configuration from a .env file using Dotenv.
     *
     * @param string|null $path Path to the directory containing .env file
     */
    public function loadDotEnv(?string $path = null): self
    {
        try {
            // Default to project root or current directory
            $path ??= $this->findProjectRoot();

            // Check if .env file exists
            if (file_exists($path . '/.env')) {
                // Load .env file
                $dotenv = Dotenv::createImmutable($path);
                $dotenv->load();

                // Reload environment variables after loading .env
                $this->loadFromEnvironment();
            }
        } catch (\Throwable) {
            // Silently ignore errors to prevent issues when .env is not available
            // This means we'll fall back to system environment variables and defaults
        }

        return $this;
    }

    /**
     * Attempt to find the project root directory.
     */
    private function findProjectRoot(): string
    {
        // Try to find composer.json going up from current directory
        $dir = dirname(__DIR__); // Start from src directory

        while ($dir !== '/' && $dir !== '.') {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        // Fall back to current directory
        return getcwd() ?: '.';
    }

    /**
     * Load configuration from a file.
     *
     * @param string $path Path to the configuration file (PHP, JSON, or INI)
     * @throws PhpSwarmException If the file doesn't exist or can't be loaded
     */
    public function loadFromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new PhpSwarmException("Configuration file not found: $path");
        }

        $fileExtension = pathinfo($path, PATHINFO_EXTENSION);

        $configData = match ($fileExtension) {
            'php' => $this->loadFromPhpFile($path),
            'json' => $this->loadFromJsonFile($path),
            'ini' => $this->loadFromIniFile($path),
            default => throw new PhpSwarmException("Unsupported configuration file type: $fileExtension"),
        };

        foreach ($configData as $key => $value) {
            $this->set($key, $value);
        }

        $this->configPath = $path;

        return $this;
    }

    /**
     * Load configuration from a PHP file.
     *
     * @param string $path Path to the PHP file
     * @return array<string, mixed>
     * @throws PhpSwarmException If the file doesn't return an array
     */
    private function loadFromPhpFile(string $path): array
    {
        $config = require $path;

        if (!is_array($config)) {
            throw new PhpSwarmException("PHP configuration file must return an array");
        }

        return $this->flattenArray($config);
    }

    /**
     * Load configuration from a JSON file.
     *
     * @param string $path Path to the JSON file
     * @return array<string, mixed>
     * @throws PhpSwarmException If the JSON is invalid
     */
    private function loadFromJsonFile(string $path): array
    {
        $json = file_get_contents($path);

        if ($json === false) {
            throw new PhpSwarmException("Failed to read JSON configuration file");
        }

        $config = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new PhpSwarmException("Invalid JSON in configuration file: " . json_last_error_msg());
        }

        return $this->flattenArray($config);
    }

    /**
     * Load configuration from an INI file.
     *
     * @param string $path Path to the INI file
     * @return array<string, mixed>
     * @throws PhpSwarmException If the INI is invalid
     */
    private function loadFromIniFile(string $path): array
    {
        $config = parse_ini_file($path, true);

        if ($config === false) {
            throw new PhpSwarmException("Failed to parse INI configuration file");
        }

        return $this->flattenArray($config);
    }

    /**
     * Load configuration from environment variables.
     */
    public function loadFromEnvironment(): self
    {
        $prefix = 'PHPSWARM_';

        foreach ($_ENV as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $configKey = strtolower(str_replace($prefix, '', $key));
                $configKey = str_replace('_', '.', $configKey);
                $this->set($configKey, $value);
            }
        }

        // Check getenv() for environments that don't set $_ENV
        foreach (array_keys($this->defaults) as $defaultKey) {
            $envKey = $prefix . strtoupper(str_replace('.', '_', $defaultKey));
            $envValue = getenv($envKey);

            if ($envValue !== false) {
                $this->set($defaultKey, $envValue);
            }
        }

        // Load specific API keys
        $apiKeys = [
            'OPENAI_API_KEY' => 'llm.openai.api_key',
            'ANTHROPIC_API_KEY' => 'llm.anthropic.api_key',
            'SEARCH_API_KEY' => 'tool.web_search.api_key',
            'SEARCH_ENGINE_ID' => 'tool.web_search.engine_id',
            'WEATHER_API_KEY' => 'tool.weather.api_key',
        ];

        foreach ($apiKeys as $envKey => $configKey) {
            $value = getenv($envKey);
            if ($value !== false) {
                $this->set($configKey, $value);
            }
        }

        return $this;
    }

    /**
     * Get a configuration value.
     *
     * @param string $key The configuration key (dot notation)
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set a configuration value.
     *
     * @param string $key The configuration key (dot notation)
     * @param mixed $value The configuration value
     */
    public function set(string $key, mixed $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key The configuration key (dot notation)
     * @return bool Whether the key exists
     */
    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    /**
     * Get all configuration values.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Save the current configuration to a file.
     *
     * @param string|null $path Path to save to (defaults to the loaded file)
     * @return bool Whether the save was successful
     * @throws PhpSwarmException If no path is provided and no file was loaded
     */
    public function save(?string $path = null): bool
    {
        $path ??= $this->configPath;

        if ($path === null) {
            throw new PhpSwarmException("No configuration file path specified");
        }

        $fileExtension = pathinfo($path, PATHINFO_EXTENSION);

        $success = match ($fileExtension) {
            'php' => $this->saveToPhpFile($path),
            'json' => $this->saveToJsonFile($path),
            'ini' => $this->saveToIniFile($path),
            default => throw new PhpSwarmException("Unsupported configuration file type: $fileExtension"),
        };

        if ($success) {
            $this->configPath = $path;
        }

        return $success;
    }

    /**
     * Save configuration to a PHP file.
     *
     * @param string $path Path to save to
     * @return bool Whether the save was successful
     */
    private function saveToPhpFile(string $path): bool
    {
        $unflattenedConfig = $this->unflattenArray($this->config);
        $phpCode = "<?php\n\nreturn " . $this->varExport($unflattenedConfig, true) . ";\n";

        return file_put_contents($path, $phpCode) !== false;
    }

    /**
     * Save configuration to a JSON file.
     *
     * @param string $path Path to save to
     * @return bool Whether the save was successful
     */
    private function saveToJsonFile(string $path): bool
    {
        $unflattenedConfig = $this->unflattenArray($this->config);
        $json = json_encode($unflattenedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents($path, $json) !== false;
    }

    /**
     * Save configuration to an INI file.
     *
     * @param string $path Path to save to
     * @return bool Whether the save was successful
     */
    private function saveToIniFile(string $path): bool
    {
        $unflattenedConfig = $this->unflattenArray($this->config);
        $iniContent = '';

        foreach ($unflattenedConfig as $section => $values) {
            $iniContent .= "[$section]\n";

            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $iniContent .= "$key.$subKey = " . $this->formatIniValue($subValue) . "\n";
                    }
                } else {
                    $iniContent .= "$key = " . $this->formatIniValue($value) . "\n";
                }
            }

            $iniContent .= "\n";
        }

        return file_put_contents($path, $iniContent) !== false;
    }

    /**
     * Format a value for an INI file.
     *
     * @param mixed $value The value to format
     * @return string The formatted value
     */
    private function formatIniValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return '';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return '"' . str_replace('"', '\\"', (string) $value) . '"';
    }

    /**
     * Flatten a multi-dimensional array into a dot notation array.
     *
     * @param array<string, mixed> $array The array to flatten
     * @param string $prefix The current key prefix
     * @return array<string, mixed> The flattened array
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix !== '' && $prefix !== '0' ? $prefix . '.' . $key : $key;

            if (is_array($value) && $value !== [] && $this->isAssociativeArray($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Unflatten a dot notation array into a multi-dimensional array.
     *
     * @param array<string, mixed> $array The array to unflatten
     * @return array<string, mixed> The unflattened array
     */
    private function unflattenArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $keyParts = explode('.', $key);
            $this->setArrayValue($result, $keyParts, $value);
        }

        return $result;
    }

    /**
     * Set a value in a multi-dimensional array using an array of keys.
     *
     * @param array<string, mixed> $array The array to modify
     * @param array<int, string> $keys The key parts
     * @param mixed $value The value to set
     */
    private function setArrayValue(array &$array, array $keys, mixed $value): void
    {
        $key = array_shift($keys);

        if ($keys === []) {
            $array[$key] = $value;
        } else {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $this->setArrayValue($array[$key], $keys, $value);
        }
    }

    /**
     * Check if an array is associative.
     *
     * @param array<mixed> $array The array to check
     * @return bool Whether the array is associative
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Alternative to var_export() that uses short array syntax.
     *
     * @param mixed $var The variable to export
     * @param bool $return Whether to return the result or print it
     * @return string|null The exported variable or null if printed
     */
    private function varExport(mixed $var, bool $return = false): ?string
    {
        $export = var_export($var, true);
        $export = preg_replace("/array \(([^()])/", "[$1", $export);
        $export = preg_replace("/\)$/", "]", (string) $export);
        $export = str_replace("array (", "[", $export);
        $export = str_replace("=> \n", "=> ", $export);

        if ($return) {
            return $export;
        }

        echo $export;
        return null;
    }
}
