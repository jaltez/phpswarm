<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Agent;

/**
 * Interface for responses returned by agents after task execution.
 */
interface AgentResponseInterface
{
    /**
     * Get the original task that was given to the agent.
     *
     * @return string
     */
    public function getTask(): string;
    
    /**
     * Get the final answer or result produced by the agent.
     *
     * @return string
     */
    public function getFinalAnswer(): string;
    
    /**
     * Get the complete execution trace/steps taken by the agent.
     *
     * @return array<mixed>
     */
    public function getTrace(): array;
    
    /**
     * Get the execution time in seconds.
     *
     * @return float
     */
    public function getExecutionTime(): float;
    
    /**
     * Get whether the agent completed the task successfully.
     *
     * @return bool
     */
    public function isSuccessful(): bool;
    
    /**
     * Get any error messages that occurred during execution.
     *
     * @return string|null
     */
    public function getError(): ?string;
    
    /**
     * Get the token usage information.
     *
     * @return array<string, int>
     */
    public function getTokenUsage(): array;
    
    /**
     * Get any additional metadata about the execution.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;
} 