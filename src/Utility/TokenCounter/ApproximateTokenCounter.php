<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\TokenCounter;

/**
 * Approximate token counter for unknown models or fallback scenarios.
 * 
 * Provides basic token counting using general estimation rules when
 * provider-specific counters are not available.
 */
class ApproximateTokenCounter extends BaseTokenCounter
{
    /**
     * @var array<string, int> Generic model context limits
     */
    protected array $contextLimits = [
        'default' => 4096,
        'small' => 2048,
        'medium' => 8192,
        'large' => 16384,
        'xlarge' => 32768,
        'xxlarge' => 65536,
        'huge' => 131072,
    ];

    /**
     * @var float The default character-to-token ratio
     */
    private float $defaultRatio = 3.8;

    /**
     * @var array<string, float> Language-specific adjustment factors
     */
    private array $languageFactors = [
        'code' => 0.8,      // Code is typically more token-dense
        'json' => 0.9,      // JSON has structure overhead
        'xml' => 0.85,      // XML has tag overhead
        'markdown' => 1.1,  // Markdown is typically less dense
        'natural' => 1.0,   // Natural language baseline
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

        // Use general estimation algorithm
        $tokenCount = $this->estimateTokensGeneral($text);

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

        // Use same algorithm but track the model
        $tokenCount = $this->estimateTokensGeneral($text);
        $this->updateUsageStats($tokenCount);

        return $tokenCount;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getProviderName(): string
    {
        return 'Generic';
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
     * Set a custom context limit for a model.
     *
     * @param string $model The model name
     * @param int $contextLimit The context limit in tokens
     */
    public function setModelContextLimit(string $model, int $contextLimit): void
    {
        $this->contextLimits[$model] = $contextLimit;
    }

    /**
     * Set a custom character-to-token ratio.
     *
     * @param float $ratio The character-to-token ratio
     */
    public function setTokenRatio(float $ratio): void
    {
        $this->defaultRatio = $ratio;
    }

    /**
     * Estimate tokens using general approximation algorithm.
     *
     * @param string $text The text to count tokens for
     * @return int The estimated number of tokens
     */
    private function estimateTokensGeneral(string $text): int
    {
        // Step 1: Detect content type for language-specific adjustments
        $contentType = $this->detectContentType($text);
        $languageFactor = $this->languageFactors[$contentType] ?? 1.0;

        // Step 2: Basic character count with ratio
        $charCount = mb_strlen($text);
        $basicEstimate = (int) ceil($charCount / $this->defaultRatio);

        // Step 3: Apply language-specific adjustment
        $adjustedEstimate = (int) ceil($basicEstimate * $languageFactor);

        // Step 4: Adjust for whitespace patterns
        $whitespaceAdjustment = $this->calculateWhitespaceAdjustment($text);

        // Step 5: Adjust for special characters
        $specialCharAdjustment = $this->calculateSpecialCharAdjustment($text);

        // Step 6: Adjust for repetitive patterns
        $repetitionAdjustment = $this->calculateRepetitionAdjustment($text);

        $totalEstimate = $adjustedEstimate + $whitespaceAdjustment +
            $specialCharAdjustment + $repetitionAdjustment;

        // Ensure minimum of 1 token for non-empty strings
        return max(1, $totalEstimate);
    }

    /**
     * Detect the content type of the text.
     *
     * @param string $text The text to analyze
     * @return string The detected content type
     */
    private function detectContentType(string $text): string
    {
        $text = mb_strtolower($text);

        // Check for code patterns
        if ($this->hasCodePatterns($text)) {
            return 'code';
        }

        // Check for JSON
        if ($this->isJson($text)) {
            return 'json';
        }

        // Check for XML
        if ($this->hasXmlPatterns($text)) {
            return 'xml';
        }

        // Check for Markdown
        if ($this->hasMarkdownPatterns($text)) {
            return 'markdown';
        }

        return 'natural';
    }

    /**
     * Check if text contains code patterns.
     *
     * @param string $text The text to check
     * @return bool Whether the text appears to be code
     */
    private function hasCodePatterns(string $text): bool
    {
        $codeIndicators = [
            '/function\s+\w+\s*\(/',
            '/class\s+\w+/',
            '/def\s+\w+\s*\(/',
            '/\$\w+\s*=/',
            '/\w+\s*:\s*\w+/',
            '/import\s+\w+/',
            '/require\s*\(/',
            '/console\.log\s*\(/',
            '/\w+\.\w+\s*\(/',
        ];

        foreach ($codeIndicators as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if text is JSON.
     *
     * @param string $text The text to check
     * @return bool Whether the text is JSON
     */
    private function isJson(string $text): bool
    {
        $trimmed = trim($text);
        return (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
            (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'));
    }

    /**
     * Check if text contains XML patterns.
     *
     * @param string $text The text to check
     * @return bool Whether the text appears to be XML
     */
    private function hasXmlPatterns(string $text): bool
    {
        return preg_match('/<\w+[^>]*>/', $text) ||
            preg_match('/<\/\w+>/', $text);
    }

    /**
     * Check if text contains Markdown patterns.
     *
     * @param string $text The text to check
     * @return bool Whether the text appears to be Markdown
     */
    private function hasMarkdownPatterns(string $text): bool
    {
        $markdownIndicators = [
            '/^#+\s/',          // Headers
            '/\*\*\w+\*\*/',    // Bold
            '/_\w+_/',          // Italic
            '/\[.*\]\(.*\)/',   // Links
            '/```/',            // Code blocks
            '/^\*\s/',          // Bullet points
            '/^\d+\.\s/',       // Numbered lists
        ];

        foreach ($markdownIndicators as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate adjustment for whitespace patterns.
     *
     * @param string $text The text to analyze
     * @return int The whitespace adjustment
     */
    private function calculateWhitespaceAdjustment(string $text): int
    {
        $lineBreaks = substr_count($text, "\n");
        $tabs = substr_count($text, "\t");
        $doubleSpaces = substr_count($text, "  ");

        // Each significant whitespace pattern adds ~0.3 tokens
        return (int) ceil(($lineBreaks + $tabs + $doubleSpaces) * 0.3);
    }

    /**
     * Calculate adjustment for special characters.
     *
     * @param string $text The text to analyze
     * @return int The special character adjustment
     */
    private function calculateSpecialCharAdjustment(string $text): int
    {
        $specialChars = [
            '@',
            '#',
            '$',
            '%',
            '^',
            '&',
            '*',
            '(',
            ')',
            '[',
            ']',
            '{',
            '}',
            '|',
            '\\',
            '/',
            '<',
            '>'
        ];

        $count = 0;
        foreach ($specialChars as $char) {
            $count += substr_count($text, $char);
        }

        // Special characters often get separate tokens
        return (int) ceil($count * 0.2);
    }

    /**
     * Calculate adjustment for repetitive patterns.
     *
     * @param string $text The text to analyze
     * @return int The repetition adjustment
     */
    private function calculateRepetitionAdjustment(string $text): int
    {
        // Check for highly repetitive patterns
        $words = explode(' ', $text);
        $wordCount = count($words);
        $uniqueWords = count(array_unique($words));

        if ($wordCount > 10) {
            $repetitionRatio = $uniqueWords / $wordCount;

            // If very repetitive, tokens may be compressed
            if ($repetitionRatio < 0.3) {
                return (int) -ceil($wordCount * 0.1); // Negative adjustment
            }
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function getDefaultContextLength(): int
    {
        return $this->contextLimits['default'];
    }
}
