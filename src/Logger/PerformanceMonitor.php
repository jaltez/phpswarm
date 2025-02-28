<?php

declare(strict_types=1);

namespace PhpSwarm\Logger;

use PhpSwarm\Contract\Logger\LoggerInterface;
use PhpSwarm\Contract\Logger\MonitorInterface;

/**
 * Performance monitoring implementation for the PHPSwarm system.
 */
class PerformanceMonitor implements MonitorInterface
{
    /**
     * @var array<string, mixed> Metrics storage
     */
    private array $metrics = [];

    /**
     * @var array<string, array<string, mixed>> Active timers
     */
    private array $timers = [];

    /**
     * @var array<string, array<string, mixed>> Active processes
     */
    private array $processes = [];

    /**
     * @var array<string, array<string, mixed>> Completed processes
     */
    private array $completedProcesses = [];

    /**
     * @var LoggerInterface|null Logger for recording events
     */
    private ?LoggerInterface $logger;

    /**
     * Create a new PerformanceMonitor instance.
     *
     * @param LoggerInterface|null $logger Optional logger to record events
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function startTimer(string $operation, array $context = []): string
    {
        $timerId = uniqid('timer_', true);

        $this->timers[$timerId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'context' => $context,
        ];

        if ($this->logger) {
            $this->logger->debug("Started timer for '$operation'", ['timer_id' => $timerId] + $context);
        }

        return $timerId;
    }

    /**
     * {@inheritdoc}
     */
    public function stopTimer(string $timerId, array $context = []): float
    {
        if (!isset($this->timers[$timerId])) {
            throw new \InvalidArgumentException("Timer with ID '$timerId' not found");
        }

        $timer = $this->timers[$timerId];
        $endTime = microtime(true);
        $elapsed = $endTime - $timer['start_time'];
        $operation = $timer['operation'];

        // Store the result
        $this->recordMetric("timer.$operation", $elapsed, [
            'timer_id' => $timerId,
            'start_time' => $timer['start_time'],
            'end_time' => $endTime,
            'duration' => $elapsed,
        ] + $timer['context'] + $context);

        // Add to the operation's times list
        if (!isset($this->metrics["timer_history.$operation"])) {
            $this->metrics["timer_history.$operation"] = [];
        }

        $this->metrics["timer_history.$operation"][] = [
            'timestamp' => time(),
            'duration' => $elapsed,
            'context' => $timer['context'] + $context,
        ];

        // Update average if needed
        if (!isset($this->metrics["timer_avg.$operation"])) {
            $this->metrics["timer_avg.$operation"] = $elapsed;
            $this->metrics["timer_count.$operation"] = 1;
        } else {
            $count = $this->metrics["timer_count.$operation"];
            $currentAvg = $this->metrics["timer_avg.$operation"];
            $newAvg = (($currentAvg * $count) + $elapsed) / ($count + 1);
            $this->metrics["timer_avg.$operation"] = $newAvg;
            $this->metrics["timer_count.$operation"] = $count + 1;
        }

        // Log the result
        if ($this->logger) {
            $this->logger->debug(
                "Stopped timer for '$operation': {$elapsed}s",
                ['timer_id' => $timerId, 'duration' => $elapsed] + $context
            );
        }

        // Clean up
        unset($this->timers[$timerId]);

        return $elapsed;
    }

    /**
     * {@inheritdoc}
     */
    public function recordMetric(string $name, mixed $value, array $context = []): void
    {
        $this->metrics[$name] = $value;

        if ($this->logger) {
            $this->logger->debug("Recorded metric '$name'", ['value' => $value] + $context);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function incrementCounter(string $name, int $increment = 1, array $context = []): int
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = 0;
        } elseif (!is_int($this->metrics[$name])) {
            throw new \InvalidArgumentException("Metric '$name' exists but is not an integer counter");
        }

        $this->metrics[$name] += $increment;

        if ($this->logger) {
            $this->logger->debug(
                "Incremented counter '$name' by $increment",
                ['value' => $this->metrics[$name]] + $context
            );
        }

        return $this->metrics[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function beginProcess(string $processName, array $context = []): string
    {
        $processId = uniqid('process_', true);

        $this->processes[$processId] = [
            'name' => $processName,
            'start_time' => microtime(true),
            'context' => $context,
            'status' => 'running',
        ];

        // Increment process count
        $this->incrementCounter("process_count.$processName");
        $this->incrementCounter('process_count.total');
        $this->incrementCounter('process_active');

        if ($this->logger) {
            $this->logger->info("Started process '$processName'", ['process_id' => $processId] + $context);
        }

        return $processId;
    }

    /**
     * {@inheritdoc}
     */
    public function endProcess(string $processId, array $context = []): void
    {
        if (!isset($this->processes[$processId])) {
            throw new \InvalidArgumentException("Process with ID '$processId' not found");
        }

        $process = $this->processes[$processId];
        $processName = $process['name'];
        $endTime = microtime(true);
        $duration = $endTime - $process['start_time'];

        // Update process data
        $process['end_time'] = $endTime;
        $process['duration'] = $duration;
        $process['context'] = array_merge($process['context'], $context);
        $process['status'] = 'completed';

        // Store completed process
        $this->completedProcesses[$processId] = $process;

        // Track success rate
        $this->incrementCounter("process_success.$processName");
        $this->incrementCounter('process_success.total');

        // Decrement active count
        $this->incrementCounter('process_active', -1);

        // Calculate success rate
        $successCount = $this->metrics["process_success.$processName"] ?? 0;
        $totalCount = $this->metrics["process_count.$processName"] ?? 1;
        $this->metrics["process_success_rate.$processName"] = $successCount / $totalCount;

        // Store duration
        $this->recordMetric("process_last_duration.$processName", $duration);

        // Update average duration
        if (!isset($this->metrics["process_avg_duration.$processName"])) {
            $this->metrics["process_avg_duration.$processName"] = $duration;
            $this->metrics["process_completed_count.$processName"] = 1;
        } else {
            $count = $this->metrics["process_completed_count.$processName"];
            $this->metrics["process_avg_duration.$processName"] =
                (($this->metrics["process_avg_duration.$processName"] * $count) + $duration) / ($count + 1);
            $this->metrics["process_completed_count.$processName"] = $count + 1;
        }

        if ($this->logger) {
            $this->logger->info(
                "Completed process '$processName' in {$duration}s",
                ['process_id' => $processId, 'duration' => $duration] + $context
            );
        }

        // Clean up
        unset($this->processes[$processId]);
    }

    /**
     * {@inheritdoc}
     */
    public function failProcess(string $processId, string $reason, array $context = []): void
    {
        if (!isset($this->processes[$processId])) {
            throw new \InvalidArgumentException("Process with ID '$processId' not found");
        }

        $process = $this->processes[$processId];
        $processName = $process['name'];
        $endTime = microtime(true);
        $duration = $endTime - $process['start_time'];

        // Update process data
        $process['end_time'] = $endTime;
        $process['duration'] = $duration;
        $process['context'] = array_merge($process['context'], $context);
        $process['status'] = 'failed';
        $process['failure_reason'] = $reason;

        // Store completed process
        $this->completedProcesses[$processId] = $process;

        // Track failure rate
        $this->incrementCounter("process_failure.$processName");
        $this->incrementCounter('process_failure.total');

        // Decrement active count
        $this->incrementCounter('process_active', -1);

        // Calculate failure rate
        $failureCount = $this->metrics["process_failure.$processName"] ?? 0;
        $totalCount = $this->metrics["process_count.$processName"] ?? 1;
        $this->metrics["process_failure_rate.$processName"] = $failureCount / $totalCount;

        if ($this->logger) {
            $this->logger->error(
                "Failed process '$processName' after {$duration}s: $reason",
                ['process_id' => $processId, 'duration' => $duration, 'reason' => $reason] + $context
            );
        }

        // Clean up
        unset($this->processes[$processId]);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetric(string $name): mixed
    {
        return $this->metrics[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function clearMetrics(): void
    {
        $this->metrics = [];
        $this->timers = [];
        $this->processes = [];
        $this->completedProcesses = [];

        if ($this->logger) {
            $this->logger->debug('Cleared all metrics');
        }
    }

    /**
     * Get all active timers.
     *
     * @return array<string, array<string, mixed>> The active timers
     */
    public function getActiveTimers(): array
    {
        return $this->timers;
    }

    /**
     * Get all active processes.
     *
     * @return array<string, array<string, mixed>> The active processes
     */
    public function getActiveProcesses(): array
    {
        return $this->processes;
    }

    /**
     * Get all completed processes.
     *
     * @return array<string, array<string, mixed>> The completed processes
     */
    public function getCompletedProcesses(): array
    {
        return $this->completedProcesses;
    }

    /**
     * Get completed processes for a specific name.
     *
     * @param string $processName The name of the process
     * @return array<string, array<string, mixed>> The completed processes for that name
     */
    public function getCompletedProcessesByName(string $processName): array
    {
        return array_filter(
            $this->completedProcesses,
            fn($process) => $process['name'] === $processName
        );
    }
}
