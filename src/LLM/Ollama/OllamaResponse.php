<?php

declare(strict_types=1);

namespace PhpSwarm\LLM\Ollama;

use PhpSwarm\Contract\LLM\LLMResponseInterface;

/**
 * Response implementation for Ollama API responses.
 */
class OllamaResponse implements LLMResponseInterface
{
    /**
     * @var array<string, mixed> The raw response data from Ollama
     */
    private array $rawResponse;

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
     * Create a new OllamaResponse instance.
     *
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(array $rawResponse)
    {
        $this->rawResponse = $rawResponse;
        $this->parseResponse();
    }

    /**
     * Parse the raw response data.
     */
    private function parseResponse(): void
    {
        // Extract model information
        $this->model = $this->rawResponse['model'] ?? 'unknown';

        // Extract content
        if (isset($this->rawResponse['message']['content'])) {
            // Chat response
            $this->content = $this->rawResponse['message']['content'];
        } elseif (isset($this->rawResponse['response'])) {
            // Generate response
            $this->content = $this->rawResponse['response'];
        } else {
            $this->content = '';
        }

        // Extract token usage estimates
        if (isset($this->rawResponse['prompt_eval_count'])) {
            $this->promptTokens = $this->rawResponse['prompt_eval_count'];
        }

        if (isset($this->rawResponse['eval_count'])) {
            $this->completionTokens = $this->rawResponse['eval_count'];
        }

        if ($this->promptTokens !== null && $this->completionTokens !== null) {
            $this->totalTokens = $this->promptTokens + $this->completionTokens;
        }

        // Set finish reason
        if (isset($this->rawResponse['done_reason'])) {
            $this->finishReason = $this->rawResponse['done_reason'];
        } elseif (isset($this->rawResponse['done']) && $this->rawResponse['done'] === true) {
            $this->finishReason = 'stop';
        }

        // Additional metadata
        if (isset($this->rawResponse['total_duration'])) {
            $this->metadata['total_duration'] = $this->rawResponse['total_duration'];
        }

        if (isset($this->rawResponse['load_duration'])) {
            $this->metadata['load_duration'] = $this->rawResponse['load_duration'];
        }

        if (isset($this->rawResponse['created_at'])) {
            $this->metadata['created_at'] = $this->rawResponse['created_at'];
        }
    }

    /**
     * Get the main text/content of the response.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the raw response data from the LLM provider.
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * Get the tool calls from the response, if any.
     *
     * @return array<array<string, mixed>>
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Get whether the response contains tool calls.
     *
     * @return bool
     */
    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    /**
     * Get the number of prompt tokens used.
     *
     * @return int|null
     */
    public function getPromptTokens(): ?int
    {
        return $this->promptTokens;
    }

    /**
     * Get the number of completion tokens used.
     *
     * @return int|null
     */
    public function getCompletionTokens(): ?int
    {
        return $this->completionTokens;
    }

    /**
     * Get the total number of tokens used.
     *
     * @return int|null
     */
    public function getTotalTokens(): ?int
    {
        return $this->totalTokens;
    }

    /**
     * Get the model used for this response.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get any additional metadata about the response.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the finish reason provided by the LLM.
     *
     * @return string|null
     */
    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    /**
     * Add additional metadata to the response.
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