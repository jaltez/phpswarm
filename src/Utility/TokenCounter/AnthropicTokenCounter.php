<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\TokenCounter;

/**
 * Token counter optimized for Anthropic Claude models.
 * 
 * Provides more accurate token counting for Anthropic Claude models by implementing
 * Claude-specific tokenization patterns and formatting rules.
 */
class AnthropicTokenCounter extends BaseTokenCounter
{
    /**
     * @var array<string, int> Anthropic model context limits
     */
    protected array $contextLimits = [
        'claude-3-opus-20240229' => 200000,
        'claude-3-sonnet-20240229' => 200000,
        'claude-3-haiku-20240307' => 200000,
        'claude-2.1' => 200000,
        'claude-2.0' => 100000,
        'claude-instant-1.2' => 100000,
        'claude-instant-1.1' => 100000,
        'claude-instant-1.0' => 100000,
        'claude-1.3' => 100000,
        'claude-1.2' => 100000,
        'claude-1.0' => 100000,
    ];

    /**
     * @var array<string, float> Token-to-character ratios by model family
     */
    private array $tokenRatios = [
        'claude-3' => 3.8,
        'claude-2' => 4.2,
        'claude-instant' => 4.0,
        'claude-1' => 4.5,
    ];

    /**
     * @var array<string> Claude-specific formatting tokens
     */
    private array $claudeTokens = [
        'Human:',
        'Assistant:',
        '\n\n',
        '\n',
        '\t',
        '```',
        '**',
        '*',
        '_',
        '`',
        '<thinking>',
        '</thinking>',
        '<search_term>',
        '</search_term>',
    ];

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function countTokens(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Use Claude-optimized estimation algorithm
        $tokenCount = $this->estimateTokensClaude($text);

        $this->updateUsageStats($tokenCount);

        return $tokenCount;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function countTokensForModel(string $text, string $model): int
    {
        $this->trackModel($model);

        if (empty($text)) {
            return 0;
        }

        $tokenCount = $this->estimateTokensForClaudeModel($text, $model);
        $this->updateUsageStats($tokenCount);

        return $tokenCount;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getProviderName(): string
    {
        return 'Anthropic';
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getSupportedModels(): array
    {
        return array_keys($this->contextLimits);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function getRoleTokens(string $role): int
    {
        // Claude uses different formatting than OpenAI
        return match ($role) {
            'system' => 2,     // Less overhead in Claude
            'user' => 4,       // "Human: " prefix
            'assistant' => 6,  // "Assistant: " prefix
            'function' => 3,
            'tool' => 3,
            default => 2,
        };
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function getSystemTokens(int $messageCount): int
    {
        // Claude has different conversation formatting
        // Each turn typically adds ~2-3 tokens for structure
        return $messageCount * 2 + 1;
    }

    /**
     * Estimate tokens using Claude-optimized algorithm.
     *
     * @param string $text The text to count tokens for
     * @return int The estimated number of tokens
     */
    private function estimateTokensClaude(string $text): int
    {
        // Multi-step estimation optimized for Claude

        // Step 1: Basic character count with Claude ratio
        $charCount = mb_strlen($text);
        $basicEstimate = (int) ceil($charCount / 4.0);

        // Step 2: Adjust for Claude-specific formatting
        $claudeFormatCount = $this->countClaudeFormats($text);
        $formatAdjustment = (int) ceil($claudeFormatCount * 0.5);

        // Step 3: Adjust for thinking blocks (Claude-specific)
        $thinkingAdjustment = $this->detectThinkingBlocks($text);

        // Step 4: Adjust for XML-like tags (common in Claude usage)
        $xmlAdjustment = $this->detectXmlTags($text);

        // Step 5: Adjust for longer context patterns (Claude handles these differently)
        $contextAdjustment = $this->adjustForLongContext($text);

        $totalEstimate = $basicEstimate + $formatAdjustment + $thinkingAdjustment +
            $xmlAdjustment + $contextAdjustment;

        // Ensure minimum of 1 token for non-empty strings
        return max(1, $totalEstimate);
    }

    /**
     * Estimate tokens for a specific Claude model.
     *
     * @param string $text The text to count tokens for
     * @param string $model The model name
     * @return int The estimated number of tokens
     */
    private function estimateTokensForClaudeModel(string $text, string $model): int
    {
        $baseEstimate = $this->estimateTokensClaude($text);

        // Apply model-specific adjustments
        $modelFamily = $this->getClaudeModelFamily($model);
        $ratio = $this->tokenRatios[$modelFamily] ?? 4.0;

        // Recalculate with model-specific ratio
        $charCount = mb_strlen($text);
        $modelSpecificEstimate = (int) ceil($charCount / $ratio);

        // Claude 3 models are more efficient, use lower estimate
        if (str_starts_with($model, 'claude-3')) {
            return min($baseEstimate, $modelSpecificEstimate);
        }

        // For older models, use more conservative estimate
        return max($baseEstimate, $modelSpecificEstimate);
    }

    /**
     * Count Claude-specific formatting elements.
     *
     * @param string $text The text to analyze
     * @return int The count of Claude formatting elements
     */
    private function countClaudeFormats(string $text): int
    {
        $count = 0;
        foreach ($this->claudeTokens as $token) {
            $count += substr_count($text, $token);
        }
        return $count;
    }

    /**
     * Detect and count thinking blocks (Claude-specific feature).
     *
     * @param string $text The text to analyze
     * @return int The adjustment for thinking blocks
     */
    private function detectThinkingBlocks(string $text): int
    {
        $thinkingPattern = '/<thinking>[\s\S]*?<\/thinking>/i';
        $matches = preg_match_all($thinkingPattern, $text);

        // Thinking blocks typically add extra tokenization overhead
        return ($matches ?: 0) * 3;
    }

    /**
     * Detect XML-like tags commonly used with Claude.
     *
     * @param string $text The text to analyze
     * @return int The adjustment for XML tags
     */
    private function detectXmlTags(string $text): int
    {
        $xmlPatterns = [
            '/<[^>]+>/',           // Generic XML tags
            '/<\/[^>]+>/',         // Closing XML tags
            '/\[INST\]/',          // Instruction tags
            '/\[\/INST\]/',        // Closing instruction tags
            '/<document>/',        // Document tags
            '/<\/document>/',      // Closing document tags
            '/<example>/',         // Example tags
            '/<\/example>/',       // Closing example tags
        ];

        $xmlCount = 0;
        foreach ($xmlPatterns as $pattern) {
            $matches = preg_match_all($pattern, $text);
            $xmlCount += $matches ?: 0;
        }

        // XML tags typically count as individual tokens
        return $xmlCount;
    }

    /**
     * Adjust for long context patterns that Claude handles efficiently.
     *
     * @param string $text The text to analyze
     * @return int The adjustment for long context
     */
    private function adjustForLongContext(string $text): int
    {
        $textLength = mb_strlen($text);

        // For very long texts, Claude's tokenization is more efficient
        if ($textLength > 10000) {
            return (int) -ceil($textLength / 1000); // Negative adjustment
        }

        return 0;
    }

    /**
     * Get the Claude model family for ratio calculation.
     *
     * @param string $model The model name
     * @return string The model family
     */
    private function getClaudeModelFamily(string $model): string
    {
        if (str_starts_with($model, 'claude-3')) {
            return 'claude-3';
        }
        if (str_starts_with($model, 'claude-2')) {
            return 'claude-2';
        }
        if (str_starts_with($model, 'claude-instant')) {
            return 'claude-instant';
        }
        if (str_starts_with($model, 'claude-1')) {
            return 'claude-1';
        }

        return 'claude-2'; // Default fallback
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function getDefaultContextLength(): int
    {
        return 100000; // Claude models typically have large context windows
    }
}
