<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Agent\Agent;
use PhpSwarm\LLM\OpenAI\OpenAIConnector;
use PhpSwarm\Tool\Calculator\CalculatorTool;
use PhpSwarm\Tool\WebSearch\WebSearchTool;
use PhpSwarm\Tool\Weather\WeatherTool;
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

// Create tools
$calculatorTool = new CalculatorTool();

$webSearchTool = new WebSearchTool([
    'api_key' => getenv('SEARCH_API_KEY'),
    'search_engine_id' => getenv('SEARCH_ENGINE_ID'),
    'service' => 'google'
]);

$weatherTool = new WeatherTool([
    'api_key' => getenv('WEATHER_API_KEY'),
    'service' => 'openweathermap'
]);

// Create a memory system that will persist across interactions
$memory = new ArrayMemory();

// Create a research assistant agent with multiple tools
$agent = Agent::create()
    ->withName('Research Assistant')
    ->withRole('Assistant')
    ->withGoal('Help users answer complex questions requiring multiple tools and types of information')
    ->withBackstory('I am an advanced AI research assistant that can use multiple tools to gather information, perform calculations, and analyze data to provide accurate and comprehensive responses.')
    ->withLLM($llm)
    ->withMemory($memory)
    ->addTool($calculatorTool)
    ->addTool($webSearchTool)
    ->addTool($weatherTool)
    ->withVerboseLogging(true)
    ->build();

// Function to handle user input and display responses
function chatWithAgent(Agent $agent) {
    echo "Research Assistant is ready! Type 'exit' to quit.\n";
    echo "Example queries you could try:\n";
    echo "1. What's the current weather in New York and convert the temperature to Fahrenheit?\n";
    echo "2. Research quantum computing and calculate how many qubits would be needed to factor a 1024-bit number?\n";
    echo "3. What's the population of Tokyo and what percentage is that of Japan's total population?\n\n";
    
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
        
        echo "Processing (this might take a moment)...\n";
        
        // Run the agent with the user's input
        $startTime = microtime(true);
        $response = $agent->run($input);
        $endTime = microtime(true);
        
        // Display the response
        echo "\nResponse:\n" . $response->getFinalAnswer() . "\n";
        
        // Display some stats
        echo "\n--- Stats ---\n";
        echo "Processing time: " . round(($endTime - $startTime), 2) . " seconds\n";
        
        if ($tokenUsage = $response->getTokenUsage()) {
            echo "Token usage: " . json_encode($tokenUsage) . "\n";
        }
        
        // Add to memory for context in future interactions
        $agent->getMemory()->add(
            'interaction_' . time(),
            [
                'query' => $input,
                'response' => $response->getFinalAnswer(),
            ],
            ['timestamp' => new \DateTimeImmutable()]
        );
    }
}

// Show warning about API keys
$missingKeys = [];
if (empty(getenv('SEARCH_API_KEY'))) $missingKeys[] = 'SEARCH_API_KEY';
if (empty(getenv('SEARCH_ENGINE_ID'))) $missingKeys[] = 'SEARCH_ENGINE_ID';
if (empty(getenv('WEATHER_API_KEY'))) $missingKeys[] = 'WEATHER_API_KEY';

if (!empty($missingKeys)) {
    echo "Warning: The following environment variables are not set:\n";
    foreach ($missingKeys as $key) {
        echo "- $key\n";
    }
    echo "Some tools may not function properly without these variables.\n";
    echo "Continue anyway? (y/n): ";
    $answer = trim(fgets(STDIN));
    if (strtolower($answer) !== 'y') {
        exit(0);
    }
}

// Start the chat interface
chatWithAgent($agent); 