<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Event;

use PhpSwarm\Contract\Tool\ToolInterface;
use PhpSwarm\Contract\Utility\EventInterface;
use DateTimeInterface;

/**
 * Event related to tool operations.
 */
class ToolEvent extends Event implements EventInterface
{
    // Tool event types
    public const TOOL_EXECUTED = 'tool.executed';
    public const TOOL_EXECUTION_STARTED = 'tool.execution.started';
    public const TOOL_EXECUTION_COMPLETED = 'tool.execution.completed';
    public const TOOL_EXECUTION_FAILED = 'tool.execution.failed';
    public const TOOL_VALIDATION_FAILED = 'tool.validation.failed';

    /**
     * Create a new tool event.
     *
     * @param string $name The event name
     * @param ToolInterface $tool The tool involved in the event
     * @param array<string, mixed> $data Additional event data
     * @param bool $stoppable Whether the event can be stopped
     * @param DateTimeInterface|null $timestamp The event timestamp
     */
    public function __construct(
        string $name,
        private readonly ToolInterface $tool,
        array $data = [],
        bool $stoppable = true,
        ?DateTimeInterface $timestamp = null
    ) {
        $enrichedData = array_merge([
            'tool_name' => $tool->getName(),
            'tool_description' => $tool->getDescription(),
            'tool_tags' => $tool->getTags(),
        ], $data);

        parent::__construct($name, $enrichedData, 'tool', $stoppable, $timestamp);
    }

    /**
     * Get the tool associated with this event.
     */
    public function getTool(): ToolInterface
    {
        return $this->tool;
    }

    /**
     * Create an execution started event.
     *
     * @param ToolInterface $tool The tool
     * @param array<string, mixed> $parameters The execution parameters
     */
    public static function executionStarted(ToolInterface $tool, array $parameters = []): self
    {
        return new self(self::TOOL_EXECUTION_STARTED, $tool, [
            'parameters' => $parameters,
        ]);
    }

    /**
     * Create an execution completed event.
     *
     * @param ToolInterface $tool The tool
     * @param mixed $result The execution result
     * @param float $executionTime Execution time in seconds
     * @param array<string, mixed> $parameters The execution parameters
     */
    public static function executionCompleted(ToolInterface $tool, mixed $result = null, float $executionTime = 0.0, array $parameters = []): self
    {
        return new self(self::TOOL_EXECUTION_COMPLETED, $tool, [
            'result' => $result,
            'execution_time' => $executionTime,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Create an execution failed event.
     *
     * @param ToolInterface $tool The tool
     * @param \Throwable $error The error that occurred
     * @param array<string, mixed> $parameters The execution parameters
     */
    public static function executionFailed(ToolInterface $tool, \Throwable $error, array $parameters = []): self
    {
        return new self(self::TOOL_EXECUTION_FAILED, $tool, [
            'error' => $error->getMessage(),
            'error_class' => get_class($error),
            'error_code' => $error->getCode(),
            'parameters' => $parameters,
        ]);
    }

    /**
     * Create a validation failed event.
     *
     * @param ToolInterface $tool The tool
     * @param array<string> $validationErrors The validation errors
     * @param array<string, mixed> $parameters The parameters that failed validation
     */
    public static function validationFailed(ToolInterface $tool, array $validationErrors, array $parameters = []): self
    {
        return new self(self::TOOL_VALIDATION_FAILED, $tool, [
            'validation_errors' => $validationErrors,
            'parameters' => $parameters,
        ]);
    }
}
