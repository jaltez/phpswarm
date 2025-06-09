<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Event;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Utility\EventInterface;
use DateTimeInterface;

/**
 * Event related to agent operations.
 */
class AgentEvent extends Event implements EventInterface
{
    // Agent event types
    public const AGENT_CREATED = 'agent.created';
    public const AGENT_TASK_STARTED = 'agent.task.started';
    public const AGENT_TASK_COMPLETED = 'agent.task.completed';
    public const AGENT_TASK_FAILED = 'agent.task.failed';
    public const AGENT_TOOL_CALLED = 'agent.tool.called';
    public const AGENT_MEMORY_ACCESSED = 'agent.memory.accessed';
    public const AGENT_LLM_REQUEST = 'agent.llm.request';
    public const AGENT_LLM_RESPONSE = 'agent.llm.response';

    /**
     * Create a new agent event.
     *
     * @param string $name The event name
     * @param AgentInterface $agent The agent involved in the event
     * @param array<string, mixed> $data Additional event data
     * @param bool $stoppable Whether the event can be stopped
     * @param DateTimeInterface|null $timestamp The event timestamp
     */
    public function __construct(
        string $name,
        private readonly AgentInterface $agent,
        array $data = [],
        bool $stoppable = true,
        ?DateTimeInterface $timestamp = null
    ) {
        $enrichedData = array_merge([
            'agent_name' => $agent->getName(),
            'agent_role' => $agent->getRole(),
            'agent_goal' => $agent->getGoal(),
        ], $data);

        parent::__construct($name, $enrichedData, 'agent', $stoppable, $timestamp);
    }

    /**
     * Get the agent associated with this event.
     */
    public function getAgent(): AgentInterface
    {
        return $this->agent;
    }

    /**
     * Create a task started event.
     *
     * @param AgentInterface $agent The agent
     * @param string $task The task being started
     * @param array<string, mixed> $context Task context
     */
    public static function taskStarted(AgentInterface $agent, string $task, array $context = []): self
    {
        return new self(self::AGENT_TASK_STARTED, $agent, [
            'task' => $task,
            'context' => $context,
        ]);
    }

    /**
     * Create a task completed event.
     *
     * @param AgentInterface $agent The agent
     * @param string $task The task that was completed
     * @param mixed $result The task result
     * @param float $executionTime Execution time in seconds
     */
    public static function taskCompleted(AgentInterface $agent, string $task, mixed $result = null, float $executionTime = 0.0): self
    {
        return new self(self::AGENT_TASK_COMPLETED, $agent, [
            'task' => $task,
            'result' => $result,
            'execution_time' => $executionTime,
        ]);
    }

    /**
     * Create a task failed event.
     *
     * @param AgentInterface $agent The agent
     * @param string $task The task that failed
     * @param \Throwable $error The error that occurred
     */
    public static function taskFailed(AgentInterface $agent, string $task, \Throwable $error): self
    {
        return new self(self::AGENT_TASK_FAILED, $agent, [
            'task' => $task,
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
            'error_code' => $error->getCode(),
        ]);
    }

    /**
     * Create a tool called event.
     *
     * @param AgentInterface $agent The agent
     * @param string $toolName The name of the tool called
     * @param array<string, mixed> $parameters The tool parameters
     */
    public static function toolCalled(AgentInterface $agent, string $toolName, array $parameters = []): self
    {
        return new self(self::AGENT_TOOL_CALLED, $agent, [
            'tool_name' => $toolName,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Create an LLM request event.
     *
     * @param AgentInterface $agent The agent
     * @param string $prompt The prompt sent to LLM
     * @param array<string, mixed> $options LLM options
     */
    public static function llmRequest(AgentInterface $agent, string $prompt, array $options = []): self
    {
        return new self(self::AGENT_LLM_REQUEST, $agent, [
            'prompt' => $prompt,
            'options' => $options,
        ]);
    }

    /**
     * Create an LLM response event.
     *
     * @param AgentInterface $agent The agent
     * @param string $response The response from LLM
     * @param array<string, mixed> $usage Token usage information
     */
    public static function llmResponse(AgentInterface $agent, string $response, array $usage = []): self
    {
        return new self(self::AGENT_LLM_RESPONSE, $agent, [
            'response' => $response,
            'token_usage' => $usage,
        ]);
    }
}
