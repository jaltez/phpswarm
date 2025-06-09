<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Event;

use PhpSwarm\Contract\Workflow\WorkflowInterface;
use PhpSwarm\Contract\Utility\EventInterface;
use DateTimeInterface;

/**
 * Event related to workflow operations.
 */
class WorkflowEvent extends Event implements EventInterface
{
    // Workflow event types
    public const WORKFLOW_STARTED = 'workflow.started';
    public const WORKFLOW_COMPLETED = 'workflow.completed';
    public const WORKFLOW_FAILED = 'workflow.failed';
    public const WORKFLOW_STEP_STARTED = 'workflow.step.started';
    public const WORKFLOW_STEP_COMPLETED = 'workflow.step.completed';
    public const WORKFLOW_STEP_FAILED = 'workflow.step.failed';
    public const WORKFLOW_STEP_SKIPPED = 'workflow.step.skipped';

    /**
     * Create a new workflow event.
     *
     * @param string $name The event name
     * @param WorkflowInterface $workflow The workflow involved in the event
     * @param array<string, mixed> $data Additional event data
     * @param bool $stoppable Whether the event can be stopped
     * @param DateTimeInterface|null $timestamp The event timestamp
     */
    public function __construct(
        string $name,
        private readonly WorkflowInterface $workflow,
        array $data = [],
        bool $stoppable = true,
        ?DateTimeInterface $timestamp = null
    ) {
        $enrichedData = array_merge([
            'workflow_name' => $workflow->getName(),
            'workflow_steps_count' => count($workflow->getSteps()),
        ], $data);

        parent::__construct($name, $enrichedData, 'workflow', $stoppable, $timestamp);
    }

    /**
     * Get the workflow associated with this event.
     */
    public function getWorkflow(): WorkflowInterface
    {
        return $this->workflow;
    }

    /**
     * Create a workflow started event.
     *
     * @param WorkflowInterface $workflow The workflow
     * @param array<string, mixed> $context Workflow context
     */
    public static function workflowStarted(WorkflowInterface $workflow, array $context = []): self
    {
        return new self(self::WORKFLOW_STARTED, $workflow, [
            'context' => $context,
        ]);
    }

    /**
     * Create a workflow completed event.
     *
     * @param WorkflowInterface $workflow The workflow
     * @param mixed $result The workflow result
     * @param float $executionTime Execution time in seconds
     */
    public static function workflowCompleted(WorkflowInterface $workflow, mixed $result = null, float $executionTime = 0.0): self
    {
        return new self(self::WORKFLOW_COMPLETED, $workflow, [
            'result' => $result,
            'execution_time' => $executionTime,
        ]);
    }

    /**
     * Create a workflow failed event.
     *
     * @param WorkflowInterface $workflow The workflow
     * @param \Throwable $error The error that occurred
     * @param string|null $failedStep The step that failed
     */
    public static function workflowFailed(WorkflowInterface $workflow, \Throwable $error, ?string $failedStep = null): self
    {
        return new self(self::WORKFLOW_FAILED, $workflow, [
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
            'error_code' => $error->getCode(),
            'failed_step' => $failedStep,
        ]);
    }

    /**
     * Create a step started event.
     *
     * @param WorkflowInterface $workflow The workflow
     * @param string $stepName The step name
     * @param array<string, mixed> $stepData Step data
     */
    public static function stepStarted(WorkflowInterface $workflow, string $stepName, array $stepData = []): self
    {
        return new self(self::WORKFLOW_STEP_STARTED, $workflow, [
            'step_name' => $stepName,
            'step_data' => $stepData,
        ]);
    }

    /**
     * Create a step completed event.
     *
     * @param WorkflowInterface $workflow The workflow
     * @param string $stepName The step name
     * @param mixed $result The step result
     * @param float $executionTime Step execution time in seconds
     */
    public static function stepCompleted(WorkflowInterface $workflow, string $stepName, mixed $result = null, float $executionTime = 0.0): self
    {
        return new self(self::WORKFLOW_STEP_COMPLETED, $workflow, [
            'step_name' => $stepName,
            'result' => $result,
            'execution_time' => $executionTime,
        ]);
    }

    /**
     * Create a step failed event.
     *
     * @param WorkflowInterface $workflow The workflow
     * @param string $stepName The step name
     * @param \Throwable $error The error that occurred
     */
    public static function stepFailed(WorkflowInterface $workflow, string $stepName, \Throwable $error): self
    {
        return new self(self::WORKFLOW_STEP_FAILED, $workflow, [
            'step_name' => $stepName,
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
            'error_code' => $error->getCode(),
        ]);
    }
}
