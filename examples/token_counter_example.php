<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Factory\PhpSwarmFactory;
use PhpSwarm\Utility\TokenCounter\TokenCounterFactory;
use PhpSwarm\Utility\TokenCounter\OpenAITokenCounter;
use PhpSwarm\Utility\TokenCounter\AnthropicTokenCounter;
use PhpSwarm\Utility\TokenCounter\ApproximateTokenCounter;

// ===========================
// Token Counter Example
// ===========================

echo "🔢 PHPSwarm Token Counter Example\n";
echo "==================================\n\n";

try {
    // Sample texts for testing
    $sampleTexts = [
        'simple' => "Hello, world! How are you today?",
        'code' => '<?php
function calculateTokens($text) {
    return ceil(strlen($text) / 4);
}

$message = "This is a test message";
$tokens = calculateTokens($message);
echo "Token count: " . $tokens;
',
        'json' => '{"name": "John Doe", "age": 30, "email": "john@example.com", "preferences": {"theme": "dark", "language": "en"}}',
        'markdown' => '# Token Counter
This is a **markdown** document with:
- Bullet points
- *Italic text*
- Code blocks: `tokenCounter.count(text)`

## Features
1. OpenAI-specific counting
2. Anthropic-specific counting
3. Generic approximation',
        'chat_messages' => [
            ['role' => 'system', 'content' => 'You are a helpful AI assistant.'],
            ['role' => 'user', 'content' => 'Can you help me count tokens in my text?'],
            ['role' => 'assistant', 'content' => 'Of course! I can help you count tokens. What text would you like me to analyze?'],
        ]
    ];

    // Example 1: Using Factory to create counters
    echo "📦 1. Creating Token Counters via Factory\n";
    echo "-" . str_repeat("-", 45) . "\n";

    $factory = new PhpSwarmFactory();

    // Create different types of counters
    $openaiCounter = $factory->createTokenCounter('openai');
    $anthropicCounter = $factory->createTokenCounter('anthropic');
    $genericCounter = $factory->createTokenCounter('generic');

    echo "✅ Created OpenAI counter: " . $openaiCounter->getProviderName() . "\n";
    echo "✅ Created Anthropic counter: " . $anthropicCounter->getProviderName() . "\n";
    echo "✅ Created Generic counter: " . $genericCounter->getProviderName() . "\n\n";

    // Example 2: Direct factory usage
    echo "🏭 2. Using TokenCounterFactory Directly\n";
    echo "-" . str_repeat("-", 45) . "\n";

    $gpt4Counter = TokenCounterFactory::createForModel('gpt-4');
    $claudeCounter = TokenCounterFactory::createForModel('claude-3-sonnet-20240229');
    $bestCounter = TokenCounterFactory::createBest();

    echo "✅ Counter for GPT-4: " . $gpt4Counter->getProviderName() . "\n";
    echo "✅ Counter for Claude: " . $claudeCounter->getProviderName() . "\n";
    echo "✅ Best available counter: " . $bestCounter->getProviderName() . "\n\n";

    // Example 3: Counting tokens in different text types
    echo "📊 3. Token Counting Comparison\n";
    echo "-" . str_repeat("-", 45) . "\n";

    $counters = [
        'OpenAI' => $openaiCounter,
        'Anthropic' => $anthropicCounter,
        'Generic' => $genericCounter,
    ];

    foreach ($sampleTexts as $textType => $text) {
        if ($textType === 'chat_messages') {
            continue; // Skip for now, we'll handle this separately
        }

        echo "Text type: " . ucfirst($textType) . "\n";
        echo "Content preview: " . substr((string)$text, 0, 50) . "...\n";

        foreach ($counters as $name => $counter) {
            $tokenCount = $counter->countTokens((string)$text);
            echo "  {$name}: {$tokenCount} tokens\n";
        }
        echo "\n";
    }

    // Example 4: Chat message token counting
    echo "💬 4. Chat Message Token Counting\n";
    echo "-" . str_repeat("-", 45) . "\n";

    $chatMessages = $sampleTexts['chat_messages'];

    foreach ($counters as $name => $counter) {
        $tokenCount = $counter->countChatTokens($chatMessages);
        echo "{$name} chat tokens: {$tokenCount}\n";
    }
    echo "\n";

    // Example 5: Model-specific token counting
    echo "🎯 5. Model-Specific Token Counting\n";
    echo "-" . str_repeat("-", 45) . "\n";

    $testText = $sampleTexts['code'];
    $models = ['gpt-4', 'gpt-3.5-turbo', 'claude-3-sonnet-20240229', 'claude-2.1'];

    foreach ($models as $model) {
        $counter = TokenCounterFactory::createForModel($model);
        $tokenCount = $counter->countTokensForModel($testText, $model);
        $maxContext = $counter->getMaxContextLength($model);

        echo "Model: {$model}\n";
        echo "  Tokens: {$tokenCount}\n";
        echo "  Max context: {$maxContext}\n";
        echo "  Fits in context: " . ($counter->fitsInContext($testText, $model) ? "Yes" : "No") . "\n\n";
    }

    // Example 6: Context management
    echo "🔧 6. Context Management Features\n";
    echo "-" . str_repeat("-", 45) . "\n";

    $longText = str_repeat($sampleTexts['simple'], 200); // Make it very long
    $model = 'gpt-3.5-turbo';
    $counter = TokenCounterFactory::createForModel($model);

    $originalTokens = $counter->countTokensForModel($longText, $model);
    $maxContext = $counter->getMaxContextLength($model);

    echo "Original text tokens: {$originalTokens}\n";
    echo "Model max context: {$maxContext}\n";
    echo "Fits in context: " . ($counter->fitsInContext($longText, $model) ? "Yes" : "No") . "\n";

    if (!$counter->fitsInContext($longText, $model)) {
        $truncated = $counter->truncateToContext($longText, $model, 1000);
        $truncatedTokens = $counter->countTokensForModel($truncated, $model);

        echo "Truncated to: {$truncatedTokens} tokens (with 1000 reserved)\n";
        echo "Truncated text preview: " . substr($truncated, 0, 100) . "...\n";
    }
    echo "\n";

    // Example 7: Usage statistics
    echo "📈 7. Usage Statistics\n";
    echo "-" . str_repeat("-", 45) . "\n";

    foreach ($counters as $name => $counter) {
        $stats = $counter->getUsageStats();
        echo "{$name} Counter Statistics:\n";
        echo "  Total tokens counted: " . $stats['total_tokens_counted'] . "\n";
        echo "  Requests processed: " . $stats['requests_processed'] . "\n";
        echo "  Unique models used: " . $stats['unique_models_used'] . "\n";
        if (isset($stats['tracked_models']) && !empty($stats['tracked_models'])) {
            echo "  Models: " . implode(', ', $stats['tracked_models']) . "\n";
        }
        echo "\n";
    }

    // Example 8: Custom configurations
    echo "⚙️ 8. Custom Configurations\n";
    echo "-" . str_repeat("-", 45) . "\n";

    $customCounter = new ApproximateTokenCounter();
    $customCounter->setTokenRatio(3.0); // Custom ratio
    $customCounter->setModelContextLimit('custom-model', 8192);

    echo "Custom counter provider: " . $customCounter->getProviderName() . "\n";
    echo "Supported models: " . implode(', ', $customCounter->getSupportedModels()) . "\n";

    $customTokens = $customCounter->countTokensForModel($sampleTexts['simple'], 'custom-model');
    echo "Custom counting result: {$customTokens} tokens\n";
    echo "Max context for custom model: " . $customCounter->getMaxContextLength('custom-model') . "\n\n";

    // Example 9: Factory registration
    echo "🔧 9. Factory Registration\n";
    echo "-" . str_repeat("-", 45) . "\n";

    // Register a custom model
    TokenCounterFactory::registerModel('my-custom-gpt', 'openai');

    echo "Registered custom model: my-custom-gpt\n";
    echo "Supported providers: " . implode(', ', TokenCounterFactory::getSupportedProviders()) . "\n";
    echo "Model support check: " . (TokenCounterFactory::supportsModel('my-custom-gpt') ? "Yes" : "No") . "\n\n";

    echo "✨ Token Counter Example Completed Successfully!\n";
    echo "This demonstrates comprehensive token counting capabilities for different LLM providers.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
