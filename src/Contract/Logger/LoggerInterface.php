<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Logger;

/**
 * Interface for all loggers in the PHPSwarm system.
 *
 * This interface follows PSR-3 style logging levels but is
 * specifically tailored for PHPSwarm needs.
 */
interface LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Action must be taken immediately.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Critical conditions.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Normal but significant events.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Interesting events.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Detailed debug information.
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level The log level
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void;

    /**
     * Gets the minimum logging level for this logger.
     *
     * @return string The minimum log level
     */
    public function getMinLevel(): string;

    /**
     * Sets the minimum logging level for this logger.
     *
     * @param string $level The minimum log level
     * @return self
     */
    public function setMinLevel(string $level): self;
}
