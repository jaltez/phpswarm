<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\TokenCounter;

use PhpSwarm\Contract\Utility\TokenCounterInterface;

/**
 * Factory for creating appropriate token counters.
 * 
 * Automatically selects the best token counter implementation based on
 * the provider or model being used.
 */
class TokenCounterFactory
{
    /**
     * @var array<string, class-string<TokenCounterInterface>> Provider to counter class mapping
     */
    private static array $providerMapping = [
        'openai' => OpenAITokenCounter::class,
        'anthropic' => AnthropicTokenCounter::class,
        'generic' => ApproximateTokenCounter::class,
    ];

    /**
     * @var array<string, string> Model to provider mapping for specific models
     */
    private static array $modelToProvider = [
        // OpenAI models
        'gpt-4' => 'openai',
        'gpt-4-32k' => 'openai',
        'gpt-4-turbo' => 'openai',
        'gpt-4-turbo-preview' => 'openai',
        'gpt-4-1106-preview' => 'openai',
        'gpt-4-0125-preview' => 'openai',
        'gpt-4-vision-preview' => 'openai',
        'gpt-3.5-turbo' => 'openai',
        'gpt-3.5-turbo-16k' => 'openai',
        'gpt-3.5-turbo-1106' => 'openai',
        'gpt-3.5-turbo-0125' => 'openai',
        'text-davinci-003' => 'openai',
        'text-davinci-002' => 'openai',
        'code-davinci-002' => 'openai',

        // Anthropic models
        'claude-3-opus-20240229' => 'anthropic',
        'claude-3-sonnet-20240229' => 'anthropic',
        'claude-3-haiku-20240307' => 'anthropic',
        'claude-2.1' => 'anthropic',
        'claude-2.0' => 'anthropic',
        'claude-instant-1.2' => 'anthropic',
        'claude-instant-1.1' => 'anthropic',
        'claude-instant-1.0' => 'anthropic',
    ];

    /**
     * @var array<string, TokenCounterInterface> Cache of created counter instances
     */
    private static array $counterCache = [];

    /**
     * Create a token counter for a specific provider.
     *
     * @param string $provider The provider name (openai, anthropic, etc.)
     * @return TokenCounterInterface The token counter instance
     * @throws \InvalidArgumentException If the provider is not supported
     */
    public static function createForProvider(string $provider): TokenCounterInterface
    {
        $provider = strtolower($provider);

        if (!isset(self::$providerMapping[$provider])) {
            throw new \InvalidArgumentException("Unsupported provider: {$provider}");
        }

        // Use cache to avoid creating multiple instances
        if (!isset(self::$counterCache[$provider])) {
            $counterClass = self::$providerMapping[$provider];
            self::$counterCache[$provider] = new $counterClass();
        }

        return self::$counterCache[$provider];
    }

    /**
     * Create a token counter for a specific model.
     *
     * @param string $model The model name
     * @return TokenCounterInterface The token counter instance
     */
    public static function createForModel(string $model): TokenCounterInterface
    {
        $provider = self::detectProviderFromModel($model);
        return self::createForProvider($provider);
    }

    /**
     * Create the best available token counter.
     * 
     * Falls back to the generic counter if no specific provider is available.
     *
     * @return TokenCounterInterface The token counter instance
     */
    public static function createBest(): TokenCounterInterface
    {
        return self::createForProvider('generic');
    }

    /**
     * Create an OpenAI-specific token counter.
     *
     * @return OpenAITokenCounter The OpenAI token counter
     */
    public static function createOpenAI(): OpenAITokenCounter
    {
        return self::createForProvider('openai');
    }

    /**
     * Create an Anthropic-specific token counter.
     *
     * @return AnthropicTokenCounter The Anthropic token counter
     */
    public static function createAnthropic(): AnthropicTokenCounter
    {
        return self::createForProvider('anthropic');
    }

    /**
     * Create a generic approximation token counter.
     *
     * @return ApproximateTokenCounter The approximate token counter
     */
    public static function createGeneric(): ApproximateTokenCounter
    {
        return self::createForProvider('generic');
    }

    /**
     * Register a custom provider mapping.
     *
     * @param string $provider The provider name
     * @param class-string<TokenCounterInterface> $counterClass The counter class
     */
    public static function registerProvider(string $provider, string $counterClass): void
    {
        $provider = strtolower($provider);
        self::$providerMapping[$provider] = $counterClass;

        // Clear cache for this provider
        unset(self::$counterCache[$provider]);
    }

    /**
     * Register a model-to-provider mapping.
     *
     * @param string $model The model name
     * @param string $provider The provider name
     */
    public static function registerModel(string $model, string $provider): void
    {
        self::$modelToProvider[$model] = strtolower($provider);
    }

    /**
     * Get all supported providers.
     *
     * @return array<string> List of supported provider names
     */
    public static function getSupportedProviders(): array
    {
        return array_keys(self::$providerMapping);
    }

    /**
     * Get all registered models.
     *
     * @return array<string> List of registered model names
     */
    public static function getRegisteredModels(): array
    {
        return array_keys(self::$modelToProvider);
    }

    /**
     * Check if a provider is supported.
     *
     * @param string $provider The provider name
     * @return bool Whether the provider is supported
     */
    public static function supportsProvider(string $provider): bool
    {
        return isset(self::$providerMapping[strtolower($provider)]);
    }

    /**
     * Check if a model is registered.
     *
     * @param string $model The model name
     * @return bool Whether the model is registered
     */
    public static function supportsModel(string $model): bool
    {
        return isset(self::$modelToProvider[$model]);
    }

    /**
     * Clear the counter cache.
     */
    public static function clearCache(): void
    {
        self::$counterCache = [];
    }

    /**
     * Detect the provider from a model name.
     *
     * @param string $model The model name
     * @return string The detected provider name
     */
    private static function detectProviderFromModel(string $model): string
    {
        // Check exact model mapping first
        if (isset(self::$modelToProvider[$model])) {
            return self::$modelToProvider[$model];
        }

        // Try pattern matching
        $model = strtolower($model);

        if (
            str_starts_with($model, 'gpt-') ||
            str_starts_with($model, 'text-davinci') ||
            str_starts_with($model, 'code-davinci')
        ) {
            return 'openai';
        }

        if (str_starts_with($model, 'claude')) {
            return 'anthropic';
        }

        // Default to generic
        return 'generic';
    }
}
