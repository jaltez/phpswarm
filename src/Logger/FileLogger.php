<?php

declare(strict_types=1);

namespace PhpSwarm\Logger;

use PhpSwarm\Contract\Logger\LoggerInterface;
use PhpSwarm\Exception\PhpSwarmException;

/**
 * FileLogger implementation that writes logs to a file.
 */
class FileLogger implements LoggerInterface
{
    /**
     * Log levels in ascending order of severity.
     */
    private const array LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    /**
     * @var string Minimum log level to record
     */
    private string $minLevel;

    /**
     * @var resource|null File handle for the log file
     */
    private $fileHandle;

    /**
     * Create a new FileLogger instance.
     *
     * @param string $logFile Path to the log file
     * @param string $minLevel Minimum log level to record
     * @param bool $includeTimestamps Whether to include timestamps
     * @param string $timestampFormat Format for timestamps
     * @throws PhpSwarmException If log file cannot be opened
     */
    public function __construct(
        string $logFile,
        string $minLevel = 'debug',
        private readonly bool $includeTimestamps = true,
        private readonly string $timestampFormat = 'Y-m-d H:i:s'
    ) {
        $this->setMinLevel($minLevel);

        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!file_exists($logDir) && !mkdir($logDir, 0755, true)) {
            throw new PhpSwarmException("Failed to create log directory: $logDir");
        }

        // Open log file for appending
        $this->fileHandle = fopen($logFile, 'a');
        if ($this->fileHandle === false) {
            throw new PhpSwarmException("Failed to open log file: $logFile");
        }
    }

    /**
     * Destructor to close file handle.
     */
    public function __destruct()
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function log(string $level, string $message, array $context = []): void
    {
        if (!isset(self::LEVELS[$level])) {
            throw new PhpSwarmException("Invalid log level: $level");
        }

        // Check if this level should be logged
        if (self::LEVELS[$level] < self::LEVELS[$this->minLevel]) {
            return;
        }

        // Format the log message
        $formattedMessage = $this->formatMessage($level, $message, $context);

        // Write to log file
        if ($this->fileHandle) {
            fwrite($this->fileHandle, $formattedMessage . PHP_EOL);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getMinLevel(): string
    {
        return $this->minLevel;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setMinLevel(string $level): self
    {
        if (!isset(self::LEVELS[$level])) {
            throw new PhpSwarmException("Invalid log level: $level");
        }

        $this->minLevel = $level;
        return $this;
    }

    /**
     * Format a log message.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return string Formatted message
     */
    private function formatMessage(string $level, string $message, array $context): string
    {
        $prefix = '';

        // Add timestamp if enabled
        if ($this->includeTimestamps) {
            $timestamp = date($this->timestampFormat);
            $prefix .= "[$timestamp] ";
        }

        // Add level
        $prefix .= strtoupper($level) . ': ';

        // Interpolate context into message
        $interpolatedMessage = $this->interpolate($message, $context);

        // Format context as JSON if not empty
        $contextString = '';
        if ($context !== []) {
            $contextString = ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        return $prefix . $interpolatedMessage . $contextString;
    }

    /**
     * Interpolate context values into message placeholders.
     *
     * @param string $message Message with placeholders
     * @param array<string, mixed> $context Values to replace placeholders
     * @return string Interpolated message
     */
    private function interpolate(string $message, array $context): string
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            // Skip non-scalar values
            if (!is_scalar($val) && !is_null($val)) {
                continue;
            }

            // Format the value
            $replace['{' . $key . '}'] = $val === null ? 'null' : (string) $val;
        }

        // Interpolate replacement values into the message
        return strtr($message, $replace);
    }
}
