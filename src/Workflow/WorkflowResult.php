<?php

declare(strict_types=1);

namespace PhpSwarm\Workflow;

use PhpSwarm\Contract\Workflow\WorkflowResultInterface;

/**
 * Implementation of a workflow execution result.
 */
class WorkflowResult implements WorkflowResultInterface
{
    /**
     * Create a new WorkflowResult instance.
     *
     * @param bool $success Whether the workflow was successful
     * @param array<string, mixed> $output The output from the workflow
     * @param float $executionTime The execution time in seconds
     * @param array<string, array<string, mixed>> $stepResults The results of all steps
     * @param array<string, string> $errors Any errors that occurred
     * @param array<string> $completedSteps The steps that were executed successfully
     * @param array<string> $skippedSteps The steps that were skipped
     * @param array<int, array<string, mixed>> $executionLog The execution log
     */
    public function __construct(private readonly bool $success, private array $output, private readonly float $executionTime, private array $stepResults, private array $errors, private readonly array $completedSteps, private readonly array $skippedSteps, private readonly array $executionLog)
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getStepResults(): array
    {
        return $this->stepResults;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getStepResult(string $stepId): ?array
    {
        return $this->stepResults[$stepId] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isStepSuccessful(string $stepId): bool
    {
        return in_array($stepId, $this->completedSteps, true) && !isset($this->errors[$stepId]);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getSkippedSteps(): array
    {
        return $this->skippedSteps;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getExecutionLog(): array
    {
        return $this->executionLog;
    }

    /**
     * Get a summary of the workflow execution.
     *
     * @return array<string, mixed> The summary
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->success,
            'execution_time' => $this->executionTime,
            'steps_completed' => count($this->completedSteps),
            'steps_skipped' => count($this->skippedSteps),
            'errors_count' => count($this->errors),
            'output_keys' => array_keys($this->output),
        ];
    }

    /**
     * Get a specific output value.
     *
     * @param string $key The output key
     * @param mixed $default The default value if the key doesn't exist
     * @return mixed The output value
     */
    public function getOutputValue(string $key, mixed $default = null): mixed
    {
        return $this->output[$key] ?? $default;
    }
}
