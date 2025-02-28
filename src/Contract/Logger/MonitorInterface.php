<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Logger;

/**
 * Interface for monitoring and tracking metrics in the PHPSwarm system.
 */
interface MonitorInterface
{
    /**
     * Start timing an operation.
     *
     * @param string $operation The name of the operation to time
     * @param array<string, mixed> $context Additional context data
     * @return string The timer ID
     */
    public function startTimer(string $operation, array $context = []): string;

    /**
     * Stop timing an operation and record the result.
     *
     * @param string $timerId The timer ID from startTimer
     * @param array<string, mixed> $context Additional context data
     * @return float The elapsed time in seconds
     */
    public function stopTimer(string $timerId, array $context = []): float;

    /**
     * Record a metric value.
     *
     * @param string $name The name of the metric
     * @param mixed $value The value of the metric
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function recordMetric(string $name, mixed $value, array $context = []): void;

    /**
     * Increment a counter metric.
     *
     * @param string $name The name of the counter
     * @param int $increment The amount to increment (default: 1)
     * @param array<string, mixed> $context Additional context data
     * @return int The new counter value
     */
    public function incrementCounter(string $name, int $increment = 1, array $context = []): int;

    /**
     * Record the beginning of a process or event.
     *
     * @param string $processName The name of the process
     * @param array<string, mixed> $context Additional context data
     * @return string The process ID
     */
    public function beginProcess(string $processName, array $context = []): string;

    /**
     * Record the successful completion of a process or event.
     *
     * @param string $processId The process ID from beginProcess
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function endProcess(string $processId, array $context = []): void;

    /**
     * Record the failure of a process or event.
     *
     * @param string $processId The process ID from beginProcess
     * @param string $reason The reason for the failure
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public function failProcess(string $processId, string $reason, array $context = []): void;

    /**
     * Get all recorded metrics.
     *
     * @return array<string, mixed> The metrics
     */
    public function getMetrics(): array;

    /**
     * Get a specific metric.
     *
     * @param string $name The name of the metric
     * @return mixed The metric value, or null if not found
     */
    public function getMetric(string $name): mixed;

    /**
     * Clear all metrics.
     *
     * @return void
     */
    public function clearMetrics(): void;
}
