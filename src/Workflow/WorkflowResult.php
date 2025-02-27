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
     * @var bool Whether the workflow was successful
     */
    private bool $success;
    
    /**
     * @var array<string, mixed> The output from the workflow
     */
    private array $output;
    
    /**
     * @var float The execution time in seconds
     */
    private float $executionTime;
    
    /**
     * @var array<string, array<string, mixed>> The results of all steps
     */
    private array $stepResults;
    
    /**
     * @var array<string, string> Any errors that occurred
     */
    private array $errors;
    
    /**
     * @var array<string> The steps that were executed successfully
     */
    private array $completedSteps;
    
    /**
     * @var array<string> The steps that were skipped
     */
    private array $skippedSteps;
    
    /**
     * @var array<int, array<string, mixed>> The execution log
     */
    private array $executionLog;
    
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
    public function __construct(
        bool $success,
        array $output,
        float $executionTime,
        array $stepResults,
        array $errors,
        array $completedSteps,
        array $skippedSteps,
        array $executionLog
    ) {
        $this->success = $success;
        $this->output = $output;
        $this->executionTime = $executionTime;
        $this->stepResults = $stepResults;
        $this->errors = $errors;
        $this->completedSteps = $completedSteps;
        $this->skippedSteps = $skippedSteps;
        $this->executionLog = $executionLog;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getOutput(): array
    {
        return $this->output;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getStepResults(): array
    {
        return $this->stepResults;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getStepResult(string $stepId): ?array
    {
        return $this->stepResults[$stepId] ?? null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isStepSuccessful(string $stepId): bool
    {
        return in_array($stepId, $this->completedSteps, true) && !isset($this->errors[$stepId]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSkippedSteps(): array
    {
        return $this->skippedSteps;
    }
    
    /**
     * {@inheritdoc}
     */
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