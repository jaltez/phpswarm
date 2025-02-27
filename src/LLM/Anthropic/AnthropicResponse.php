<?php

declare(strict_types=1);

namespace PhpSwarm\LLM\Anthropic;

use PhpSwarm\Contract\LLM\LLMResponseInterface;

/**
 * Response implementation for Anthropic API responses.
 */
class AnthropicResponse implements LLMResponseInterface
{
    /**
     * @var array<string, mixed> The raw response data from Anthropic
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
     * Create a new AnthropicResponse instance.
     *
     * @param array<string, mixed> $rawResponse The raw response data from Anthropic
     */
    public function __construct(array $rawResponse)
    {
        $this->rawResponse = $rawResponse;
        $this->parseResponse();
    }
    
    /**
     * Parse the raw response to extract relevant data.
     *
     * @return void
     */
    private function parseResponse(): void
    {
        // Extract the content
        $this->content = $this->rawResponse['content'][0]['text'] ?? '';
        
        // Extract tool calls if available (Claude supports tool use)
        if (isset($this->rawResponse['content']) && is_array($this->rawResponse['content'])) {
            foreach ($this->rawResponse['content'] as $content) {
                if (isset($content['type']) && $content['type'] === 'tool_use') {
                    $this->toolCalls[] = [
                        'id' => $content['id'] ?? uniqid('tool_'),
                        'function' => [
                            'name' => $content['name'] ?? '',
                            'arguments' => $content['input'] ?? '{}',
                        ],
                    ];
                }
            }
        }
        
        // Extract model
        $this->model = $this->rawResponse['model'] ?? 'unknown';
        
        // Extract token usage
        if (isset($this->rawResponse['usage']) && is_array($this->rawResponse['usage'])) {
            $this->promptTokens = $this->rawResponse['usage']['input_tokens'] ?? null;
            $this->completionTokens = $this->rawResponse['usage']['output_tokens'] ?? null;
            $this->totalTokens = isset($this->promptTokens, $this->completionTokens) 
                ? $this->promptTokens + $this->completionTokens 
                : null;
        }
        
        // Extract finish reason
        $this->finishReason = $this->rawResponse['stop_reason'] ?? null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getPromptTokens(): ?int
    {
        return $this->promptTokens;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCompletionTokens(): ?int
    {
        return $this->completionTokens;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTotalTokens(): ?int
    {
        return $this->totalTokens;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getModel(): string
    {
        return $this->model;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }
    
    /**
     * Add metadata to the response.
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