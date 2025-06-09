<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Event;

use PhpSwarm\Contract\Logger\LoggerInterface;
use PhpSwarm\Contract\Utility\EventInterface;
use PhpSwarm\Contract\Utility\EventSubscriberInterface;

/**
 * Event subscriber that logs events to the PHPSwarm logger.
 */
class LoggingEventSubscriber implements EventSubscriberInterface
{
    /**
     * Create a new logging event subscriber.
     *
     * @param LoggerInterface $logger The logger to use
     * @param bool $logAllEvents Whether to log all events or just important ones
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $logAllEvents = false
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        if ($this->logAllEvents) {
            // Log all events with different methods for different priorities
            return [
                AgentEvent::AGENT_TASK_STARTED => 'onTaskStarted',
                AgentEvent::AGENT_TASK_COMPLETED => 'onTaskCompleted',
                AgentEvent::AGENT_TASK_FAILED => ['onTaskFailed', 100], // High priority
                AgentEvent::AGENT_TOOL_CALLED => 'onToolCalled',
                AgentEvent::AGENT_LLM_REQUEST => 'onLLMRequest',
                AgentEvent::AGENT_LLM_RESPONSE => 'onLLMResponse',

                ToolEvent::TOOL_EXECUTION_STARTED => 'onToolExecutionStarted',
                ToolEvent::TOOL_EXECUTION_COMPLETED => 'onToolExecutionCompleted',
                ToolEvent::TOOL_EXECUTION_FAILED => ['onToolExecutionFailed', 100], // High priority
                ToolEvent::TOOL_VALIDATION_FAILED => ['onValidationFailed', 90],

                WorkflowEvent::WORKFLOW_STARTED => 'onWorkflowStarted',
                WorkflowEvent::WORKFLOW_COMPLETED => 'onWorkflowCompleted',
                WorkflowEvent::WORKFLOW_FAILED => ['onWorkflowFailed', 100], // High priority
                WorkflowEvent::WORKFLOW_STEP_STARTED => 'onStepStarted',
                WorkflowEvent::WORKFLOW_STEP_COMPLETED => 'onStepCompleted',
                WorkflowEvent::WORKFLOW_STEP_FAILED => ['onStepFailed', 100], // High priority
            ];
        } else {
            // Log only important events
            return [
                AgentEvent::AGENT_TASK_FAILED => ['onTaskFailed', 100],
                ToolEvent::TOOL_EXECUTION_FAILED => ['onToolExecutionFailed', 100],
                ToolEvent::TOOL_VALIDATION_FAILED => ['onValidationFailed', 90],
                WorkflowEvent::WORKFLOW_FAILED => ['onWorkflowFailed', 100],
                WorkflowEvent::WORKFLOW_STEP_FAILED => ['onStepFailed', 100],
            ];
        }
    }

    /**
     * Handle agent task started events.
     */
    public function onTaskStarted(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->info('Agent task started', [
            'agent' => $data['agent_name'] ?? 'unknown',
            'task' => $data['task'] ?? 'unknown',
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle agent task completed events.
     */
    public function onTaskCompleted(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->info('Agent task completed', [
            'agent' => $data['agent_name'] ?? 'unknown',
            'task' => $data['task'] ?? 'unknown',
            'execution_time' => $data['execution_time'] ?? 0,
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle agent task failed events.
     */
    public function onTaskFailed(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->error('Agent task failed', [
            'agent' => $data['agent_name'] ?? 'unknown',
            'task' => $data['task'] ?? 'unknown',
            'error' => $data['error'] ?? 'unknown error',
            'error_class' => $data['error_class'] ?? 'unknown',
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle tool called events.
     */
    public function onToolCalled(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->debug('Tool called', [
            'agent' => $data['agent_name'] ?? 'unknown',
            'tool' => $data['tool_name'] ?? 'unknown',
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle LLM request events.
     */
    public function onLLMRequest(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->debug('LLM request', [
            'agent' => $data['agent_name'] ?? 'unknown',
            'prompt_length' => strlen($data['prompt'] ?? ''),
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle LLM response events.
     */
    public function onLLMResponse(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->debug('LLM response', [
            'agent' => $data['agent_name'] ?? 'unknown',
            'response_length' => strlen($data['response'] ?? ''),
            'token_usage' => $data['token_usage'] ?? [],
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle tool execution started events.
     */
    public function onToolExecutionStarted(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->debug('Tool execution started', [
            'tool' => $data['tool_name'] ?? 'unknown',
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle tool execution completed events.
     */
    public function onToolExecutionCompleted(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->info('Tool execution completed', [
            'tool' => $data['tool_name'] ?? 'unknown',
            'execution_time' => $data['execution_time'] ?? 0,
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle tool execution failed events.
     */
    public function onToolExecutionFailed(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->error('Tool execution failed', [
            'tool' => $data['tool_name'] ?? 'unknown',
            'error' => $data['error'] ?? 'unknown error',
            'error_class' => $data['error_class'] ?? 'unknown',
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle validation failed events.
     */
    public function onValidationFailed(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->warning('Tool validation failed', [
            'tool' => $data['tool_name'] ?? 'unknown',
            'validation_errors' => $data['validation_errors'] ?? [],
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle workflow started events.
     */
    public function onWorkflowStarted(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->info('Workflow started', [
            'workflow' => $data['workflow_name'] ?? 'unknown',
            'steps_count' => $data['workflow_steps_count'] ?? 0,
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle workflow completed events.
     */
    public function onWorkflowCompleted(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->info('Workflow completed', [
            'workflow' => $data['workflow_name'] ?? 'unknown',
            'execution_time' => $data['execution_time'] ?? 0,
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle workflow failed events.
     */
    public function onWorkflowFailed(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->error('Workflow failed', [
            'workflow' => $data['workflow_name'] ?? 'unknown',
            'failed_step' => $data['failed_step'] ?? 'unknown',
            'error' => $data['error'] ?? 'unknown error',
            'error_class' => $data['error_class'] ?? 'unknown',
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle step started events.
     */
    public function onStepStarted(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->debug('Workflow step started', [
            'workflow' => $data['workflow_name'] ?? 'unknown',
            'step' => $data['step_name'] ?? 'unknown',
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle step completed events.
     */
    public function onStepCompleted(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->info('Workflow step completed', [
            'workflow' => $data['workflow_name'] ?? 'unknown',
            'step' => $data['step_name'] ?? 'unknown',
            'execution_time' => $data['execution_time'] ?? 0,
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Handle step failed events.
     */
    public function onStepFailed(EventInterface $event): void
    {
        $data = $event->getData();
        $this->logger->error('Workflow step failed', [
            'workflow' => $data['workflow_name'] ?? 'unknown',
            'step' => $data['step_name'] ?? 'unknown',
            'error' => $data['error'] ?? 'unknown error',
            'error_class' => $data['error_class'] ?? 'unknown',
            'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }
}
