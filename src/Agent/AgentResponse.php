<?php

declare(strict_types=1);

namespace PhpSwarm\Agent;

use PhpSwarm\Contract\Agent\AgentResponseInterface;

/**
 * Implementation of the AgentResponseInterface.
 */
class AgentResponse implements AgentResponseInterface
{
    /**
     * @var string The task given to the agent
     */
    private string $task;
    
    /**
     * @var string The final answer from the agent
     */
    private string $finalAnswer;
    
    /**
     * @var array<mixed> The execution trace
     */
    private array $trace;
    
    /**
     * @var float The execution time in seconds
     */
    private float $executionTime;
    
    /**
     * @var bool Whether the task was completed successfully
     */
    private bool $successful;
    
    /**
     * @var string|null Any error that occurred
     */
    private ?string $error;
    
    /**
     * @var array<string, int> Token usage information
     */
    private array $tokenUsage;
    
    /**
     * @var array<string, mixed> Additional metadata
     */
    private array $metadata;
    
    /**
     * Create a new AgentResponse instance.
     *
     * @param string $task The task given to the agent
     * @param string $finalAnswer The final answer from the agent
     * @param array<mixed> $trace The execution trace
     * @param float $executionTime The execution time in seconds
     * @param bool $successful Whether the task was completed successfully
     * @param string|null $error Any error that occurred
     * @param array<string, int> $tokenUsage Token usage information
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        string $task,
        string $finalAnswer,
        array $trace = [],
        float $executionTime = 0.0,
        bool $successful = true,
        ?string $error = null,
        array $tokenUsage = [],
        array $metadata = []
    ) {
        $this->task = $task;
        $this->finalAnswer = $finalAnswer;
        $this->trace = $trace;
        $this->executionTime = $executionTime;
        $this->successful = $successful;
        $this->error = $error;
        $this->tokenUsage = $tokenUsage;
        $this->metadata = $metadata;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTask(): string
    {
        return $this->task;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFinalAnswer(): string
    {
        return $this->finalAnswer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTrace(): array
    {
        return $this->trace;
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
    public function isSuccessful(): bool
    {
        return $this->successful;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getError(): ?string
    {
        return $this->error;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTokenUsage(): array
    {
        return $this->tokenUsage;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * Set the final answer.
     *
     * @param string $finalAnswer
     * @return self
     */
    public function withFinalAnswer(string $finalAnswer): self
    {
        $this->finalAnswer = $finalAnswer;
        return $this;
    }
    
    /**
     * Add to the execution trace.
     *
     * @param mixed $traceItem
     * @return self
     */
    public function addToTrace(mixed $traceItem): self
    {
        $this->trace[] = $traceItem;
        return $this;
    }
    
    /**
     * Set the execution status.
     *
     * @param bool $successful
     * @param string|null $error
     * @return self
     */
    public function withStatus(bool $successful, ?string $error = null): self
    {
        $this->successful = $successful;
        $this->error = $error;
        return $this;
    }
    
    /**
     * Add token usage information.
     *
     * @param string $key
     * @param int $count
     * @return self
     */
    public function addTokenUsage(string $key, int $count): self
    {
        $this->tokenUsage[$key] = ($this->tokenUsage[$key] ?? 0) + $count;
        return $this;
    }
    
    /**
     * Add metadata.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }
} 