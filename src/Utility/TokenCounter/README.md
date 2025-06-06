# Token Counter Utility

The Token Counter utility provides accurate token counting for different LLM providers and models, enabling proper context management and cost estimation in PHPSwarm applications.

## Features

- **Provider-Specific Counting**: Optimized algorithms for OpenAI and Anthropic models
- **Model-Aware Calculations**: Different token ratios and context limits per model
- **Context Management**: Automatic text truncation to fit within model limits
- **Usage Statistics**: Track token usage across your application
- **Factory Pattern**: Easy creation of appropriate counters
- **Extensible Design**: Support for custom providers and models

## Quick Start

### Using the Factory

```php
use PhpSwarm\Factory\PhpSwarmFactory;

$factory = new PhpSwarmFactory();

// Create provider-specific counters
$openaiCounter = $factory->createTokenCounter('openai');
$anthropicCounter = $factory->createTokenCounter('anthropic');
$genericCounter = $factory->createTokenCounter('generic');

// Count tokens
$text = "Hello, world! How are you today?";
$tokens = $openaiCounter->countTokens($text);
echo "Token count: {$tokens}";
```

### Using TokenCounterFactory Directly

```php
use PhpSwarm\Utility\TokenCounter\TokenCounterFactory;

// Create counter for specific model
$counter = TokenCounterFactory::createForModel('gpt-4');
$tokens = $counter->countTokensForModel($text, 'gpt-4');

// Create best available counter
$bestCounter = TokenCounterFactory::createBest();
```

## Available Counters

### OpenAITokenCounter

Optimized for OpenAI GPT models with:

- Model-specific token ratios (GPT-4: 3.0, GPT-3.5: 3.5 chars/token)
- Code pattern detection and adjustment
- Special character handling
- Accurate context limits for all GPT models

**Supported Models:**

- gpt-4, gpt-4-32k, gpt-4-turbo, gpt-4-turbo-preview
- gpt-3.5-turbo, gpt-3.5-turbo-16k
- text-davinci-003, text-davinci-002
- code-davinci-002

### AnthropicTokenCounter

Optimized for Anthropic Claude models with:

- Claude-specific formatting tokens
- XML tag detection (common in Claude usage)
- Thinking block recognition
- Efficient handling of long contexts

**Supported Models:**

- claude-3-opus-20240229, claude-3-sonnet-20240229, claude-3-haiku-20240307
- claude-2.1, claude-2.0
- claude-instant-1.2, claude-instant-1.1, claude-instant-1.0

### ApproximateTokenCounter

Generic fallback counter with:

- Content type detection (code, JSON, XML, Markdown)
- Language-specific adjustments
- Configurable token ratios
- Custom model support

## Core Methods

### Basic Token Counting

```php
// Count tokens in text
$tokens = $counter->countTokens($text);

// Count tokens for specific model
$tokens = $counter->countTokensForModel($text, 'gpt-4');

// Count tokens in chat messages
$messages = [
    ['role' => 'user', 'content' => 'Hello!'],
    ['role' => 'assistant', 'content' => 'Hi there!']
];
$tokens = $counter->countChatTokens($messages);
```

### Context Management

```php
// Check if text fits in model context
$fits = $counter->fitsInContext($text, 'gpt-4', 1000); // Reserve 1000 tokens

// Truncate text to fit context
$truncated = $counter->truncateToContext($text, 'gpt-4', 1000);

// Get maximum context length
$maxTokens = $counter->getMaxContextLength('gpt-4');
```

### Usage Statistics

```php
// Get usage statistics
$stats = $counter->getUsageStats();
echo "Total tokens counted: " . $stats['total_tokens_counted'];
echo "Requests processed: " . $stats['requests_processed'];
echo "Models used: " . implode(', ', $stats['tracked_models']);

// Reset statistics
$counter->resetUsageStats();
```

## Advanced Usage

### Custom Configurations

```php
use PhpSwarm\Utility\TokenCounter\ApproximateTokenCounter;

$counter = new ApproximateTokenCounter();
$counter->setTokenRatio(3.0); // Custom character-to-token ratio
$counter->setModelContextLimit('my-model', 8192); // Custom context limit
```

### Factory Registration

```php
use PhpSwarm\Utility\TokenCounter\TokenCounterFactory;

// Register custom model
TokenCounterFactory::registerModel('my-custom-gpt', 'openai');

// Register custom provider
TokenCounterFactory::registerProvider('my-provider', MyCustomTokenCounter::class);
```

### Provider Information

```php
// Get provider name
$provider = $counter->getProviderName(); // 'OpenAI', 'Anthropic', 'Generic'

// Get supported models
$models = $counter->getSupportedModels();

// Check provider/model support
$supported = TokenCounterFactory::supportsProvider('openai');
$modelSupported = TokenCounterFactory::supportsModel('gpt-4');
```

## Integration with LLM Connectors

The token counters integrate seamlessly with PHPSwarm's LLM connectors:

```php
use PhpSwarm\Factory\PhpSwarmFactory;

$factory = new PhpSwarmFactory();
$llm = $factory->createLLM(['provider' => 'openai', 'model' => 'gpt-4']);
$counter = $factory->createTokenCounterForModel('gpt-4');

// Count tokens before sending to LLM
$messages = [['role' => 'user', 'content' => 'Hello!']];
$tokenCount = $counter->countChatTokens($messages);

if ($counter->fitsInContext($messages, 'gpt-4')) {
    $response = $llm->chat($messages);
} else {
    echo "Message too long for model context!";
}
```

## Best Practices

1. **Use Model-Specific Counters**: Always use the counter that matches your LLM provider for best accuracy.

2. **Reserve Tokens**: When checking context limits, always reserve tokens for the response:

   ```php
   $fits = $counter->fitsInContext($text, $model, 1000); // Reserve 1000 tokens
   ```

3. **Monitor Usage**: Track token usage for cost estimation and optimization:

   ```php
   $stats = $counter->getUsageStats();
   $totalCost = $stats['total_tokens_counted'] * $costPerToken;
   ```

4. **Handle Long Texts**: Use truncation for texts that exceed context limits:

   ```php
   if (!$counter->fitsInContext($text, $model)) {
       $text = $counter->truncateToContext($text, $model, 1000);
   }
   ```

5. **Cache Counters**: Use the factory's built-in caching to avoid creating multiple instances:
   ```php
   $counter = TokenCounterFactory::createForModel($model); // Cached automatically
   ```

## Error Handling

```php
try {
    $counter = TokenCounterFactory::createForProvider('unsupported');
} catch (InvalidArgumentException $e) {
    echo "Provider not supported: " . $e->getMessage();
    $counter = TokenCounterFactory::createBest(); // Fallback
}
```

## Performance Considerations

- Token counting is fast but not instantaneous for very large texts
- Use caching when counting the same text multiple times
- Consider using approximate counting for non-critical applications
- The factory automatically caches counter instances to improve performance

## Examples

See `examples/token_counter_example.php` for comprehensive usage examples demonstrating all features of the token counter utility.
