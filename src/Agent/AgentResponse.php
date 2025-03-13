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
    public function __construct(private readonly string $task, private string $finalAnswer, private array $trace = [], private readonly float $executionTime = 0.0, private bool $successful = true, private ?string $error = null, private array $tokenUsage = [], private array $metadata = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTask(): string
    {
        return $this->task;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getFinalAnswer(): string
    {
        return $this->finalAnswer;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTrace(): array
    {
        return $this->trace;
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
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTokenUsage(): array
    {
        return $this->tokenUsage;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set the final answer.
     */
    public function withFinalAnswer(string $finalAnswer): self
    {
        $this->finalAnswer = $finalAnswer;
        return $this;
    }

    /**
     * Add to the execution trace.
     */
    public function addToTrace(mixed $traceItem): self
    {
        $this->trace[] = $traceItem;
        return $this;
    }

    /**
     * Set the execution status.
     */
    public function withStatus(bool $successful, ?string $error = null): self
    {
        $this->successful = $successful;
        $this->error = $error;
        return $this;
    }

    /**
     * Add token usage information.
     */
    public function addTokenUsage(string $key, int $count): self
    {
        $this->tokenUsage[$key] = ($this->tokenUsage[$key] ?? 0) + $count;
        return $this;
    }

    /**
     * Add metadata.
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }
}
