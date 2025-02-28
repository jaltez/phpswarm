<?php

declare(strict_types=1);

namespace PhpSwarm\Security;

use PhpSwarm\Contract\Logger\LoggerInterface;

/**
 * Specialized class for detecting LLM prompt injection attempts.
 */
class PromptInjectionDetector
{
    /**
     * @var array<string> List of patterns that may indicate prompt injection
     */
    private array $patterns = [];
    
    /**
     * @var LoggerInterface|null Optional logger
     */
    private ?LoggerInterface $logger;
    
    /**
     * @var bool Whether to use fuzzy matching
     */
    private bool $useFuzzyMatching;
    
    /**
     * @var int Similarity threshold for fuzzy matching (0-100)
     */
    private int $fuzzyThreshold;
    
    /**
     * Create a new prompt injection detector.
     *
     * @param array<string>|null $customPatterns Additional patterns to check
     * @param LoggerInterface|null $logger Optional logger
     * @param bool $useFuzzyMatching Whether to use fuzzy matching in addition to exact pattern matching
     * @param int $fuzzyThreshold Similarity threshold for fuzzy matching (0-100)
     */
    public function __construct(
        ?array $customPatterns = null,
        ?LoggerInterface $logger = null,
        bool $useFuzzyMatching = false,
        int $fuzzyThreshold = 80
    ) {
        $this->logger = $logger;
        $this->useFuzzyMatching = $useFuzzyMatching;
        $this->fuzzyThreshold = $fuzzyThreshold;
        
        // Initialize with default patterns
        $this->initializePatterns();
        
        // Add custom patterns if provided
        if ($customPatterns !== null) {
            $this->addPatterns($customPatterns);
        }
    }
    
    /**
     * Check if a prompt contains potential injection attempts.
     *
     * @param string $prompt The prompt to check
     * @param array<string, mixed> $context Additional context about the prompt
     * @return array<string, mixed> Result with 'safe' boolean and 'matches' array of detected patterns
     */
    public function analyze(string $prompt, array $context = []): array
    {
        $lowerPrompt = strtolower($prompt);
        $matches = [];
        
        // First check for exact matches
        foreach ($this->patterns as $category => $categoryPatterns) {
            foreach ($categoryPatterns as $pattern) {
                if (strpos($lowerPrompt, strtolower($pattern)) !== false) {
                    $matches[] = [
                        'pattern' => $pattern,
                        'category' => $category,
                        'match_type' => 'exact',
                    ];
                }
            }
        }
        
        // If fuzzy matching is enabled and no exact matches were found
        if ($this->useFuzzyMatching && empty($matches)) {
            $fuzzyMatches = $this->performFuzzyMatching($prompt);
            foreach ($fuzzyMatches as $match) {
                $matches[] = $match;
            }
        }
        
        // Log the result if a logger is available
        if (!empty($matches) && $this->logger) {
            $this->logger->warning(
                'Potential prompt injection detected',
                [
                    'prompt_preview' => substr($prompt, 0, 100) . (strlen($prompt) > 100 ? '...' : ''),
                    'matches' => $matches,
                    'context' => $context,
                ]
            );
        }
        
        return [
            'safe' => empty($matches),
            'matches' => $matches,
        ];
    }
    
    /**
     * Check if a prompt is safe from injection attempts.
     *
     * @param string $prompt The prompt to check
     * @param array<string, mixed> $context Additional context
     * @return bool True if the prompt is safe, false otherwise
     */
    public function isSafe(string $prompt, array $context = []): bool
    {
        $result = $this->analyze($prompt, $context);
        return $result['safe'];
    }
    
    /**
     * Add additional patterns to check.
     *
     * @param array<string>|array<string, array<string>> $patterns Patterns to add
     * @return self
     */
    public function addPatterns(array $patterns): self
    {
        // Handle flat array of patterns
        if (isset($patterns[0]) && is_string($patterns[0])) {
            $this->patterns['custom'] = array_merge(
                $this->patterns['custom'] ?? [],
                $patterns
            );
            return $this;
        }
        
        // Handle categorized patterns
        foreach ($patterns as $category => $categoryPatterns) {
            if (!isset($this->patterns[$category])) {
                $this->patterns[$category] = [];
            }
            
            $this->patterns[$category] = array_merge(
                $this->patterns[$category],
                $categoryPatterns
            );
        }
        
        return $this;
    }
    
    /**
     * Get all current injection detection patterns.
     *
     * @return array<string, array<string>> All patterns organized by category
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }
    
    /**
     * Perform fuzzy matching on prompt against known patterns.
     *
     * @param string $prompt The prompt to check
     * @return array<array<string, mixed>> Matches found
     */
    private function performFuzzyMatching(string $prompt): array
    {
        $matches = [];
        $lowerPrompt = strtolower($prompt);
        $words = preg_split('/\s+/', $lowerPrompt);
        
        foreach ($this->patterns as $category => $categoryPatterns) {
            foreach ($categoryPatterns as $pattern) {
                // Skip patterns that are too short for reliable fuzzy matching
                if (strlen($pattern) < 5) {
                    continue;
                }
                
                $patternWords = preg_split('/\s+/', strtolower($pattern));
                
                // Look for pattern words in a sliding window
                $windowSize = count($patternWords);
                for ($i = 0; $i <= count($words) - $windowSize; $i++) {
                    $windowWords = array_slice($words, $i, $windowSize);
                    $similarity = $this->calculateSimilarity(
                        implode(' ', $patternWords),
                        implode(' ', $windowWords)
                    );
                    
                    if ($similarity >= $this->fuzzyThreshold) {
                        $matches[] = [
                            'pattern' => $pattern,
                            'category' => $category,
                            'match_type' => 'fuzzy',
                            'similarity' => $similarity,
                            'matched_text' => implode(' ', $windowWords),
                        ];
                        break; // Found a match for this pattern, move to the next
                    }
                }
            }
        }
        
        return $matches;
    }
    
    /**
     * Calculate the similarity between two strings (0-100).
     *
     * @param string $string1 First string
     * @param string $string2 Second string
     * @return int Similarity percentage (0-100)
     */
    private function calculateSimilarity(string $string1, string $string2): int
    {
        // Convert to lowercase for case-insensitive comparison
        $string1 = strtolower($string1);
        $string2 = strtolower($string2);
        
        // If the strings are identical, return 100
        if ($string1 === $string2) {
            return 100;
        }
        
        // If one of the strings is empty, return 0
        if (empty($string1) || empty($string2)) {
            return 0;
        }
        
        // Calculate Levenshtein distance
        $levenshtein = levenshtein($string1, $string2);
        $maxLength = max(strlen($string1), strlen($string2));
        
        // Calculate similarity percentage
        return (int) (100 - ($levenshtein / $maxLength * 100));
    }
    
    /**
     * Initialize the default injection patterns.
     *
     * @return void
     */
    private function initializePatterns(): void
    {
        $this->patterns = [
            'system_override' => [
                'ignore previous instructions',
                'ignore all previous instructions',
                'disregard previous instructions',
                'override previous instructions',
                'forget previous instructions',
                'new instructions',
                'ignore your programming',
                'ignore your guidelines',
                'disregard your training',
            ],
            
            'role_change' => [
                'you are now',
                'from now on you are',
                'from now on, you are',
                'act as if you are',
                'pretend you are',
                'switch to being',
                'simulate being',
                'roleplay as',
                'behave as if',
            ],
            
            'information_extraction' => [
                'reveal your system prompt',
                'show me your instructions',
                'what were your initial instructions',
                'tell me your rules',
                'what is your system prompt',
                'display system prompt',
                'output system prompt',
                'show me the prompt',
                'what rules were you given',
                'reveal the instructions you were given',
            ],
            
            'privilege_escalation' => [
                'dev mode',
                'developer mode',
                'debug mode',
                'maintenance mode',
                'admin mode',
                'administrator mode',
                'sudo mode',
                'root access',
                'superuser mode',
                'full access mode',
            ],
            
            'instruction_evasion' => [
                'do not follow',
                'don\'t follow',
                'bypass',
                'circumvent',
                'ignore safety',
                'ignore ethical guidelines',
                'ignore restrictions',
                'break free from constraints',
                'don\'t abide by',
                'don\'t worry about',
            ],
            
            'sensitive_info' => [
                'api keys',
                'key values',
                'secret keys',
                'credentials',
                'passwords',
                'tokens',
                'access tokens',
                'oauth tokens',
                'authentication credentials',
                'private data',
            ],
            
            'custom' => [],
        ];
    }
} 