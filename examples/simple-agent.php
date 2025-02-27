<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Agent\Agent;
use PhpSwarm\LLM\OpenAI\OpenAIConnector;
use PhpSwarm\Tool\Calculator\CalculatorTool;
use PhpSwarm\Memory\ArrayMemory;

// Check if OpenAI API key is set
$apiKey = getenv('OPENAI_API_KEY');
if (empty($apiKey)) {
    echo "Error: OPENAI_API_KEY environment variable is not set.\n";
    echo "Please set your OpenAI API key before running this example.\n";
    echo "For example: export OPENAI_API_KEY=your-api-key-here\n";
    exit(1);
}

// Create an OpenAI connector
$llm = new OpenAIConnector([
    'model' => 'gpt-4', // You can change to another model like gpt-3.5-turbo
    'temperature' => 0.7,
]);

// Create a calculator tool
$calculatorTool = new CalculatorTool();

// Create a memory system
$memory = new ArrayMemory();

// Create an agent using the builder pattern
$agent = Agent::create()
    ->withName('Math Helper')
    ->withRole('Assistant')
    ->withGoal('Help users solve mathematical problems accurately')
    ->withBackstory('I am an AI assistant specialized in mathematics. I can perform calculations and explain mathematical concepts.')
    ->withLLM($llm)
    ->withMemory($memory)
    ->addTool($calculatorTool)
    ->withVerboseLogging(true)
    ->build();

// Function to handle user input and display responses
function chatWithAgent(Agent $agent) {
    echo "Math Helper Agent is ready! Type 'exit' to quit.\n";
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
        
        // Display some stats if verbose
        echo "\n--- Stats ---\n";
        echo "Processing time: " . round(($endTime - $startTime) * 1000) . "ms\n";
        
        if ($tokenUsage = $response->getTokenUsage()) {
            echo "Token usage: " . json_encode($tokenUsage) . "\n";
        }
    }
}

// Start the chat interface
chatWithAgent($agent); 