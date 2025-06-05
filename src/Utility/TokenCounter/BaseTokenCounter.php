<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\TokenCounter;

use PhpSwarm\Contract\Utility\TokenCounterInterface;

/**
 * Base abstract class for token counters.
 * 
 * Provides common functionality and structure for specific token counter implementations.
 */
abstract class BaseTokenCounter implements TokenCounterInterface
{
    /**
     * @var array<string, int> Model context limits
     */
    protected array $contextLimits = [];

    /**
     * @var array<string, int> Usage statistics
     */
    protected array $usageStats = [
        'total_tokens_counted' => 0,
        'requests_processed' => 0,
        'models_used' => 0,
    ];

    /**
     * @var array<string> Tracked models for statistics
     */
    protected array $trackedModels = [];

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function countChatTokens(array $messages): int
    {
        $totalTokens = 0;
        $systemTokens = 0; // Additional tokens for system formatting

        foreach ($messages as $message) {
            if (!isset($message['role'], $message['content'])) {
                continue;
            }

            // Add tokens for role and formatting
            $roleTokens = $this->getRoleTokens($message['role']);
            $contentTokens = $this->countTokens($message['content']);

            $totalTokens += $roleTokens + $contentTokens;
        }

        // Add system formatting tokens (varies by provider)
        $totalTokens += $this->getSystemTokens(count($messages));

        $this->updateUsageStats($totalTokens);

        return $totalTokens;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function countTokensForModel(string $text, string $model): int
    {
        $this->trackModel($model);
        return $this->countTokens($text);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getMaxContextLength(string $model): int
    {
        return $this->contextLimits[$model] ?? $this->getDefaultContextLength();
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function fitsInContext(string $text, string $model, int $reserveTokens = 1000): bool
    {
        $tokenCount = $this->countTokensForModel($text, $model);
        $maxLength = $this->getMaxContextLength($model);

        return ($tokenCount + $reserveTokens) <= $maxLength;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function truncateToContext(string $text, string $model, int $reserveTokens = 1000): string
    {
        $maxLength = $this->getMaxContextLength($model) - $reserveTokens;

        if ($this->countTokensForModel($text, $model) <= $maxLength) {
            return $text;
        }

        // Binary search to find the right truncation point
        $low = 0;
        $high = mb_strlen($text);
        $bestLength = 0;

        while ($low <= $high) {
            $mid = intval(($low + $high) / 2);
            $truncated = mb_substr($text, 0, $mid);
            $tokenCount = $this->countTokensForModel($truncated, $model);

            if ($tokenCount <= $maxLength) {
                $bestLength = $mid;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        $result = mb_substr($text, 0, $bestLength);

        // Try to end at a word boundary
        $lastSpace = mb_strrpos($result, ' ');
        if ($lastSpace !== false && $lastSpace > $bestLength * 0.8) {
            $result = mb_substr($result, 0, $lastSpace);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getUsageStats(): array
    {
        return array_merge($this->usageStats, [
            'unique_models_used' => count($this->trackedModels),
            'tracked_models' => $this->trackedModels,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function resetUsageStats(): void
    {
        $this->usageStats = [
            'total_tokens_counted' => 0,
            'requests_processed' => 0,
            'models_used' => 0,
        ];
        $this->trackedModels = [];
    }

    /**
     * Get the number of tokens added for a specific role.
     *
     * @param string $role The message role (user, assistant, system)
     * @return int The number of additional tokens for this role
     */
    protected function getRoleTokens(string $role): int
    {
        // Default implementation - can be overridden by specific providers
        return match ($role) {
            'system' => 4,
            'user' => 3,
            'assistant' => 3,
            'function' => 3,
            'tool' => 3,
            default => 2,
        };
    }

    /**
     * Get the number of system tokens added for message formatting.
     *
     * @param int $messageCount The number of messages
     * @return int The number of system tokens
     */
    protected function getSystemTokens(int $messageCount): int
    {
        // Default implementation - typically 2-3 tokens per message for formatting
        return $messageCount * 2 + 3; // Base formatting overhead
    }

    /**
     * Get the default context length when model-specific limit is not available.
     *
     * @return int The default context length
     */
    protected function getDefaultContextLength(): int
    {
        return 4096;
    }

    /**
     * Update usage statistics.
     *
     * @param int $tokenCount The number of tokens counted
     */
    protected function updateUsageStats(int $tokenCount): void
    {
        $this->usageStats['total_tokens_counted'] += $tokenCount;
        $this->usageStats['requests_processed']++;
    }

    /**
     * Track a model for statistics.
     *
     * @param string $model The model name
     */
    protected function trackModel(string $model): void
    {
        if (!in_array($model, $this->trackedModels, true)) {
            $this->trackedModels[] = $model;
            $this->usageStats['models_used']++;
        }
    }

    /**
     * Count tokens in text - to be implemented by specific providers.
     *
     * @param string $text The text to count tokens for
     * @return int The number of tokens
     */
    abstract public function countTokens(string $text): int;
}
