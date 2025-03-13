<?php

declare(strict_types=1);

namespace PhpSwarm\LLM\OpenAI;

use PhpSwarm\Contract\LLM\LLMResponseInterface;

/**
 * Response implementation for OpenAI API responses.
 */
class OpenAIResponse implements LLMResponseInterface
{
    /**
     * @var string The content/text of the response
     */
    private string $content;

    /**
     * @var array<array<string, mixed>> Tool calls extracted from the response
     */
    private array $toolCalls = [];

    /**
     * @var string The model used for the response
     */
    private string $model;

    /**
     * @var int|null The number of prompt tokens used
     */
    private ?int $promptTokens = null;

    /**
     * @var int|null The number of completion tokens used
     */
    private ?int $completionTokens = null;

    /**
     * @var int|null The total number of tokens used
     */
    private ?int $totalTokens = null;

    /**
     * @var string|null The finish reason
     */
    private ?string $finishReason = null;

    /**
     * @var array<string, mixed> Additional metadata
     */
    private array $metadata = [];

    /**
     * Create a new OpenAIResponse instance.
     *
     * @param array<string, mixed> $rawResponse The raw response data from OpenAI
     */
    public function __construct(private array $rawResponse)
    {
        $this->parseResponse();
    }

    /**
     * Parse the raw response to extract relevant data.
     */
    private function parseResponse(): void
    {
        // Extract the content
        $message = $this->rawResponse['choices'][0]['message'] ?? [];
        $this->content = $message['content'] ?? '';

        // Extract tool calls
        if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
            $this->toolCalls = $message['tool_calls'];
        }

        // Extract model
        $this->model = $this->rawResponse['model'] ?? 'unknown';

        // Extract token usage
        if (isset($this->rawResponse['usage']) && is_array($this->rawResponse['usage'])) {
            $this->promptTokens = $this->rawResponse['usage']['prompt_tokens'] ?? null;
            $this->completionTokens = $this->rawResponse['usage']['completion_tokens'] ?? null;
            $this->totalTokens = $this->rawResponse['usage']['total_tokens'] ?? null;
        }

        // Extract finish reason
        $this->finishReason = $this->rawResponse['choices'][0]['finish_reason'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getPromptTokens(): ?int
    {
        return $this->promptTokens;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getCompletionTokens(): ?int
    {
        return $this->completionTokens;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTotalTokens(): ?int
    {
        return $this->totalTokens;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getModel(): string
    {
        return $this->model;
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
     * {@inheritdoc}
     */
    #[\Override]
    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    /**
     * Add metadata to the response.
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }
}
