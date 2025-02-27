<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;

// Check if OpenAI API key is set
$apiKey = getenv('OPENAI_API_KEY');
if (empty($apiKey)) {
    echo "Error: OPENAI_API_KEY environment variable is not set.\n";
    echo "Please set your OpenAI API key before running this example.\n";
    echo "For example: export OPENAI_API_KEY=your-api-key-here\n";
    exit(1);
}

// Get the configuration instance (automatically loads from environment variables)
$config = PhpSwarmConfig::getInstance();

// Or load from a file if it exists
if (file_exists(__DIR__ . '/../config/phpswarm.php')) {
    try {
        $config->loadFromFile(__DIR__ . '/../config/phpswarm.php');
        echo "Configuration loaded from file successfully.\n";
    } catch (Exception $e) {
        echo "Warning: Failed to load configuration file: " . $e->getMessage() . "\n";
        echo "Using default configuration and environment variables.\n";
    }
}

// Create a factory using the config
$factory = new PhpSwarmFactory($config);

// Create a simple agent with the calculator tool
$agent = $factory->createAgent(
    'Math Helper',
    'Assistant',
    'Help users solve mathematical problems accurately',
    [
        'backstory' => 'I am an AI assistant specialized in mathematics. I can perform calculations and explain mathematical concepts.',
        'tools' => ['calculator'],
        'verbose_logging' => true,
    ]
);

echo "Created agent: {$agent->getName()}\n";
echo "Role: {$agent->getRole()}\n";
echo "Goal: {$agent->getGoal()}\n";
echo "Has calculator tool: " . (in_array('calculator', array_map(fn($tool) => $tool->getName(), $agent->getTools())) ? 'Yes' : 'No') . "\n";

// Function to handle user input and display responses
function chatWithAgent($agent) {
    echo "\nMath Helper Agent is ready! Type 'exit' to quit.\n";
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

// Example of creating a different agent with multiple tools
// Uncomment this section if you have the necessary API keys
/*
$researchAgent = $factory->createAgent(
    'Research Assistant',
    'Assistant',
    'Help users answer complex questions requiring multiple tools and types of information',
    [
        'backstory' => 'I am an advanced AI research assistant that can use multiple tools to gather information, perform calculations, and analyze data to provide accurate and comprehensive responses.',
        'tools' => ['calculator', 'web_search', 'weather'],
        'verbose_logging' => true,
    ]
);

echo "\nCreated another agent: {$researchAgent->getName()}\n";
echo "Role: {$researchAgent->getRole()}\n";
echo "Goal: {$researchAgent->getGoal()}\n";
echo "Number of tools: " . count($researchAgent->getTools()) . "\n";

// You could then chat with this agent as well
// chatWithAgent($researchAgent);
*/ 