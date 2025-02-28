<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Workflow;

/**
 * Interface for the result of a workflow execution.
 */
interface WorkflowResultInterface
{
    /**
     * Get the overall success status of the workflow.
     *
     * @return bool True if the workflow completed successfully
     */
    public function isSuccessful(): bool;

    /**
     * Get the output data from the workflow.
     *
     * @return array<string, mixed> The workflow output data
     */
    public function getOutput(): array;

    /**
     * Get the execution time of the workflow in seconds.
     *
     * @return float The execution time in seconds
     */
    public function getExecutionTime(): float;

    /**
     * Get the results of all executed steps.
     *
     * @return array<string, array<string, mixed>> The step results, keyed by step ID
     */
    public function getStepResults(): array;

    /**
     * Get the result of a specific step.
     *
     * @param string $stepId The step ID
     * @return array<string, mixed>|null The step result, or null if not found
     */
    public function getStepResult(string $stepId): ?array;

    /**
     * Get any errors that occurred during workflow execution.
     *
     * @return array<string, string> The errors, keyed by step ID
     */
    public function getErrors(): array;

    /**
     * Check if a specific step was successful.
     *
     * @param string $stepId The step ID
     * @return bool True if the step was successful, false otherwise
     */
    public function isStepSuccessful(string $stepId): bool;

    /**
     * Get the steps that were skipped during execution.
     *
     * @return array<string> The IDs of skipped steps
     */
    public function getSkippedSteps(): array;

    /**
     * Get the workflow execution log.
     *
     * @return array<int, array<string, mixed>> The execution log entries
     */
    public function getExecutionLog(): array;
}
