<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\TokenCounter;

/**
 * Token counter optimized for OpenAI models.
 * 
 * Provides more accurate token counting for OpenAI GPT models by implementing
 * model-specific tokenization patterns and formatting rules.
 */
class OpenAITokenCounter extends BaseTokenCounter
{
    /**
     * @var array<string, int> OpenAI model context limits
     */
    protected array $contextLimits = [
        'gpt-4' => 8192,
        'gpt-4-32k' => 32768,
        'gpt-4-turbo' => 128000,
        'gpt-4-turbo-preview' => 128000,
        'gpt-4-1106-preview' => 128000,
        'gpt-4-0125-preview' => 128000,
        'gpt-4-vision-preview' => 128000,
        'gpt-3.5-turbo' => 4096,
        'gpt-3.5-turbo-16k' => 16384,
        'gpt-3.5-turbo-1106' => 16384,
        'gpt-3.5-turbo-0125' => 16384,
        'text-davinci-003' => 4097,
        'text-davinci-002' => 4097,
        'code-davinci-002' => 8001,
    ];

    /**
     * @var array<string, float> Token-to-character ratios by model family
     */
    private array $tokenRatios = [
        'gpt-4' => 3.0,
        'gpt-3.5' => 3.5,
        'text-davinci' => 4.0,
        'code-davinci' => 3.2,
    ];

    /**
     * @var array<string> Common tokens that are often undercounted
     */
    private array $specialTokens = [
        '\n',
        '\t',
        '\r',
        '\\',
        '"',
        "'",
        '`',
        '<',
        '>',
        '{',
        '}',
        '[',
        ']',
        '(',
        ')',
        '@',
        '#',
        '$',
        '%',
        '^',
        '&',
        '*',
        '+',
        '=',
        '|',
        '~',
        ':',
        ';',
        ',',
        '.',
        '?',
        '!',
        '-',
        '_',
        '/',
        ' '
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

        // Use improved estimation algorithm
        $tokenCount = $this->estimateTokensImproved($text);

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

        $tokenCount = $this->estimateTokensForModel($text, $model);
        $this->updateUsageStats($tokenCount);

        return $tokenCount;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getProviderName(): string
    {
        return 'OpenAI';
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
        // OpenAI-specific role token overhead
        return match ($role) {
            'system' => 3,  // <|im_start|>system\n
            'user' => 3,    // <|im_start|>user\n
            'assistant' => 3, // <|im_start|>assistant\n
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
        // OpenAI system formatting overhead
        // Each message adds ~3-4 tokens for formatting
        // Plus additional tokens for conversation structure
        return $messageCount * 3 + 2;
    }

    /**
     * Estimate tokens using an improved algorithm.
     *
     * @param string $text The text to count tokens for
     * @return int The estimated number of tokens
     */
    private function estimateTokensImproved(string $text): int
    {
        // Multi-step estimation for better accuracy

        // Step 1: Basic character count estimation
        $charCount = mb_strlen($text);
        $basicEstimate = (int) ceil($charCount / 3.5);

        // Step 2: Adjust for special characters and formatting
        $specialCharCount = $this->countSpecialCharacters($text);
        $formatAdjustment = (int) ceil($specialCharCount * 0.3);

        // Step 3: Adjust for word patterns
        $wordCount = str_word_count($text);
        $wordAdjustment = (int) ceil($wordCount * 0.1);

        // Step 4: Adjust for line breaks and whitespace patterns
        $lineBreaks = substr_count($text, "\n");
        $whitespaceAdjustment = (int) ceil($lineBreaks * 0.5);

        // Step 5: Adjust for code patterns (if detected)
        $codeAdjustment = $this->detectAndAdjustForCode($text);

        $totalEstimate = $basicEstimate + $formatAdjustment + $wordAdjustment +
            $whitespaceAdjustment + $codeAdjustment;

        // Ensure minimum of 1 token for non-empty strings
        return max(1, $totalEstimate);
    }

    /**
     * Estimate tokens for a specific model.
     *
     * @param string $text The text to count tokens for
     * @param string $model The model name
     * @return int The estimated number of tokens
     */
    private function estimateTokensForModel(string $text, string $model): int
    {
        $baseEstimate = $this->estimateTokensImproved($text);

        // Apply model-specific adjustments
        $modelFamily = $this->getModelFamily($model);
        $ratio = $this->tokenRatios[$modelFamily] ?? 3.5;

        // Recalculate with model-specific ratio
        $charCount = mb_strlen($text);
        $modelSpecificEstimate = (int) ceil($charCount / $ratio);

        // Use the more conservative (higher) estimate
        return max($baseEstimate, $modelSpecificEstimate);
    }

    /**
     * Count special characters that might affect tokenization.
     *
     * @param string $text The text to analyze
     * @return int The count of special characters
     */
    private function countSpecialCharacters(string $text): int
    {
        $count = 0;
        foreach ($this->specialTokens as $token) {
            $count += substr_count($text, $token);
        }
        return $count;
    }

    /**
     * Detect code patterns and adjust token count accordingly.
     *
     * @param string $text The text to analyze
     * @return int The adjustment for code patterns
     */
    private function detectAndAdjustForCode(string $text): int
    {
        $codePatterns = [
            '/```[\s\S]*?```/',  // Code blocks
            '/`[^`]+`/',         // Inline code
            '/\{[\s\S]*?\}/',    // JSON-like structures
            '/\[[\s\S]*?\]/',    // Array-like structures
            '/function\s+\w+/',   // Function definitions
            '/class\s+\w+/',     // Class definitions
            '/import\s+/',       // Import statements
            '/require\s+/',      // Require statements
        ];

        $codeScore = 0;
        foreach ($codePatterns as $pattern) {
            $matches = preg_match_all($pattern, $text);
            $codeScore += $matches ?: 0;
        }

        // If code is detected, add extra tokens for complexity
        return $codeScore > 0 ? (int) ceil($codeScore * 2) : 0;
    }

    /**
     * Get the model family for ratio calculation.
     *
     * @param string $model The model name
     * @return string The model family
     */
    private function getModelFamily(string $model): string
    {
        if (str_starts_with($model, 'gpt-4')) {
            return 'gpt-4';
        }
        if (str_starts_with($model, 'gpt-3.5')) {
            return 'gpt-3.5';
        }
        if (str_starts_with($model, 'text-davinci')) {
            return 'text-davinci';
        }
        if (str_starts_with($model, 'code-davinci')) {
            return 'code-davinci';
        }

        return 'gpt-3.5'; // Default fallback
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function getDefaultContextLength(): int
    {
        return 4096;
    }
}
