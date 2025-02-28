<?php

declare(strict_types=1);

namespace PhpSwarm\LLM\AnthropicConnector;

use PhpSwarm\Contract\LLM\LLMResponseInterface;

/**
 * AnthropicConnectorResponse - Response from AnthropicConnector API
 */
class AnthropicConnectorResponse implements LLMResponseInterface
{
    /**
     * @var array<string, mixed> The raw response data
     */
    private array $rawResponse;

    /**
     * @var string The content of the response
     */
    private string $content = '';

    /**
     * @var array<array<string, mixed>> The tool calls in the response
     */
    private array $toolCalls = [];

    /**
     * @var bool Whether the response has tool calls
     */
    private bool $hasToolCalls = false;

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
     * @var string The model used for this response
     */
    private string $model = '';

    /**
     * @var array<string, mixed> Additional metadata about the response
     */
    private array $metadata = [];

    /**
     * @var string|null The finish reason provided by the LLM
     */
    private ?string $finishReason = null;

    /**
     * Create a new AnthropicConnectorResponse instance
     *
     * @param array<string, mixed> $rawResponse The raw response data
     */
    public function __construct(array $rawResponse)
    {
        $this->rawResponse = $rawResponse;
        $this->parseResponse();
    }

    /**
     * Parse the raw response data
     */
    private function parseResponse(): void
    {
        // Extract the model
        $this->model = $this->rawResponse['model'] ?? '';

        // Extract the content
        if (isset($this->rawResponse['choices'][0]['message']['content'])) {
            $this->content = $this->rawResponse['choices'][0]['message']['content'] ?? '';
        } elseif (isset($this->rawResponse['choices'][0]['delta']['content'])) {
            $this->content = $this->rawResponse['choices'][0]['delta']['content'] ?? '';
        }

        // Extract token usage
        if (isset($this->rawResponse['usage'])) {
            $this->promptTokens = $this->rawResponse['usage']['prompt_tokens'] ?? null;
            $this->completionTokens = $this->rawResponse['usage']['completion_tokens'] ?? null;
            $this->totalTokens = $this->rawResponse['usage']['total_tokens'] ?? null;
        }

        // Extract finish reason
        $this->finishReason = $this->rawResponse['choices'][0]['finish_reason'] ?? null;

        // Extract tool calls
        if (isset($this->rawResponse['choices'][0]['message']['tool_calls'])) {
            $this->toolCalls = $this->rawResponse['choices'][0]['message']['tool_calls'];
            $this->hasToolCalls = !empty($this->toolCalls);
        } elseif (isset($this->rawResponse['choices'][0]['delta']['tool_calls'])) {
            $this->toolCalls = $this->rawResponse['choices'][0]['delta']['tool_calls'];
            $this->hasToolCalls = !empty($this->toolCalls);
        }
    }

    /**
     * Get the main text/content of the response
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the raw response data from the LLM provider
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * Get the tool calls from the response, if any
     *
     * @return array<array<string, mixed>>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Get whether the response contains tool calls
     *
     * @return bool
     */
    public function hasToolCalls(): bool
    {
        return $this->hasToolCalls;
    }

    /**
     * Get the number of prompt tokens used
     *
     * @return int|null
     */
    public function getPromptTokens(): ?int
    {
        return $this->promptTokens;
    }

    /**
     * Get the number of completion tokens used
     *
     * @return int|null
     */
    public function getCompletionTokens(): ?int
    {
        return $this->completionTokens;
    }

    /**
     * Get the total number of tokens used
     *
     * @return int|null
     */
    public function getTotalTokens(): ?int
    {
        return $this->totalTokens;
    }

    /**
     * Get the model used for this response
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get any additional metadata about the response
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the finish reason provided by the LLM
     *
     * @return string|null
     */
    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    /**
     * Add metadata to the response
     *
     * @param string $key The metadata key
     * @param mixed $value The metadata value
     * @return self
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }
}
