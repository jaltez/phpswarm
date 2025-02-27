<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Agent\Agent;
use PhpSwarm\LLM\Anthropic\AnthropicConnector;
use PhpSwarm\Tool\Calculator\CalculatorTool;
use PhpSwarm\Memory\ArrayMemory;

// Check if Anthropic API key is set
$apiKey = getenv('ANTHROPIC_API_KEY');
if (empty($apiKey)) {
    echo "Error: ANTHROPIC_API_KEY environment variable is not set.\n";
    echo "Please set your Anthropic API key before running this example.\n";
    echo "For example: export ANTHROPIC_API_KEY=your-api-key-here\n";
    exit(1);
}

// Create an Anthropic connector
echo "Creating Anthropic connector with Claude...\n";
$llm = new AnthropicConnector([
    'model' => 'claude-3-sonnet-20240229', // You can also use claude-3-opus-20240229 or claude-3-haiku-20240307
    'temperature' => 0.7,
]);

// Create a calculator tool
$calculatorTool = new CalculatorTool();

// Create a memory system
$memory = new ArrayMemory();

// Create an agent using the builder pattern
$agent = Agent::create()
    ->withName('Claude Math Helper')
    ->withRole('Assistant')
    ->withGoal('Help users solve mathematical problems accurately')
    ->withBackstory('I am an AI assistant powered by Anthropic\'s Claude. I specialize in mathematics and can perform calculations and explain mathematical concepts.')
    ->withLLM($llm)
    ->withMemory($memory)
    ->addTool($calculatorTool)
    ->withVerboseLogging(true)
    ->build();

echo "Agent created successfully with Claude!\n";
echo "Model: " . $llm->getDefaultModel() . "\n";
echo "Provider: " . $llm->getProviderName() . "\n";
echo "Max context length: " . number_format($llm->getMaxContextLength()) . " tokens\n";
echo "Functions supported: " . ($llm->supportsFunctionCalling() ? 'Yes' : 'No') . "\n";
echo "Streaming supported: " . ($llm->supportsStreaming() ? 'Yes' : 'No') . "\n\n";

// Function to handle user input and display responses
function chatWithAgent(Agent $agent) {
    echo "Claude Math Helper Agent is ready! Type 'exit' to quit.\n";
    echo "Ask a math question like 'What is 25 * 3?' or 'Calculate the square root of 144.'\n\n";
    
    while (true) {
        echo "\n> ";
        $input = trim(fgets(STDIN));
        
        if ($input === 'exit') {
            echo "Goodbye!\n";
            break;
        }
        
        if (empty($input)) {
            continue;
        }
        
        echo "Processing...\n";
        
        // Run the agent with the user's input
        $startTime = microtime(true);
        $response = $agent->run($input);
        $endTime = microtime(true);
        
        // Display the response
        echo "\nResponse: " . $response->getFinalAnswer() . "\n";
        
        // Display some stats
        echo "\n--- Stats ---\n";
        echo "Processing time: " . round(($endTime - $startTime) * 1000) . "ms\n";
        
        if ($tokenUsage = $response->getTokenUsage()) {
            echo "Token usage: " . json_encode($tokenUsage) . "\n";
        }
    }
}

// Start the chat interface
chatWithAgent($agent); 