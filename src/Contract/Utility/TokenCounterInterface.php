<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Utility;

/**
 * Interface for token counting utilities.
 * 
 * Token counters provide accurate token counts for different LLM providers
 * and models, enabling proper context management and cost estimation.
 */
interface TokenCounterInterface
{
    /**
     * Count tokens in a text string.
     *
     * @param string $text The text to count tokens for
     * @return int The number of tokens
     */
    public function countTokens(string $text): int;

    /**
     * Count tokens in a chat message array.
     *
     * @param array<array<string, string>> $messages The messages to count tokens for
     * @return int The total number of tokens
     */
    public function countChatTokens(array $messages): int;

    /**
     * Count tokens for a specific model.
     *
     * @param string $text The text to count tokens for
     * @param string $model The model name
     * @return int The number of tokens
     */
    public function countTokensForModel(string $text, string $model): int;

    /**
     * Get the maximum context length for a model.
     *
     * @param string $model The model name
     * @return int The maximum context length in tokens
     */
    public function getMaxContextLength(string $model): int;

    /**
     * Check if the text fits within the model's context limit.
     *
     * @param string $text The text to check
     * @param string $model The model name
     * @param int $reserveTokens Tokens to reserve for response
     * @return bool Whether the text fits
     */
    public function fitsInContext(string $text, string $model, int $reserveTokens = 1000): bool;

    /**
     * Truncate text to fit within context limits.
     *
     * @param string $text The text to truncate
     * @param string $model The model name
     * @param int $reserveTokens Tokens to reserve for response
     * @return string The truncated text
     */
    public function truncateToContext(string $text, string $model, int $reserveTokens = 1000): string;

    /**
     * Get the provider name this counter is designed for.
     *
     * @return string The provider name (e.g., 'OpenAI', 'Anthropic')
     */
    public function getProviderName(): string;

    /**
     * Get supported models for this counter.
     *
     * @return array<string> List of supported model names
     */
    public function getSupportedModels(): array;

    /**
     * Get token usage statistics for the current session.
     *
     * @return array<string, int> Statistics including total tokens counted
     */
    public function getUsageStats(): array;

    /**
     * Reset the usage statistics.
     */
    public function resetUsageStats(): void;
}
