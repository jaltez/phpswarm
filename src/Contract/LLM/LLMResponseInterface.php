<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\LLM;

/**
 * Interface for responses returned by LLM connectors.
 */
interface LLMResponseInterface
{
    /**
     * Get the main text/content of the response.
     */
    public function getContent(): string;

    /**
     * Get the raw response data from the LLM provider.
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array;

    /**
     * Get the tool calls from the response, if any.
     *
     * @return array<array<string, mixed>>
     */
    public function getToolCalls(): array;

    /**
     * Get whether the response contains tool calls.
     */
    public function hasToolCalls(): bool;

    /**
     * Get the number of prompt tokens used.
     */
    public function getPromptTokens(): ?int;

    /**
     * Get the number of completion tokens used.
     */
    public function getCompletionTokens(): ?int;

    /**
     * Get the total number of tokens used.
     */
    public function getTotalTokens(): ?int;

    /**
     * Get the model used for this response.
     */
    public function getModel(): string;

    /**
     * Get any additional metadata about the response.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Get the finish reason provided by the LLM.
     */
    public function getFinishReason(): ?string;
}
