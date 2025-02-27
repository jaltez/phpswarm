<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;

// Load configuration from .env file
$config = PhpSwarmConfig::getInstance();

echo "Current configuration loaded from .env and/or environment variables:\n";
echo "---------------------------------------------------------------\n";

// Display LLM configuration
echo "LLM Provider: " . $config->get('llm.provider', 'not set') . "\n";
echo "LLM Model: " . $config->get('llm.model', 'not set') . "\n";
echo "LLM Temperature: " . $config->get('llm.temperature', 'not set') . "\n";

// Check if OpenAI API key is configured
$apiKey = $config->get('llm.openai.api_key') ?: getenv('OPENAI_API_KEY');
echo "OpenAI API Key: " . (empty($apiKey) ? 'Not set' : 'Configured (hidden for security)') . "\n";

// Display tool configuration
echo "\nTool Configuration:\n";
echo "Web Search API Key: " . 
    (empty($config->get('tool.web_search.api_key')) ? 'Not set' : 'Configured (hidden for security)') . "\n";
echo "Web Search Service: " . $config->get('tool.web_search.service', 'not set') . "\n";

echo "Weather API Key: " . 
    (empty($config->get('tool.weather.api_key')) ? 'Not set' : 'Configured (hidden for security)') . "\n";
echo "Weather Service: " . $config->get('tool.weather.service', 'not set') . "\n";

// Create a factory with the loaded configuration
$factory = new PhpSwarmFactory($config);

// Now we can create components using the factory
echo "\nCreating a calculator tool using factory...\n";
try {
    $calculatorTool = $factory->createTool('calculator');
    echo "Calculator tool created successfully!\n";
    
    // Test if we can create an agent
    echo "\nCreating a math helper agent...\n";
    $agent = $factory->createAgent(
        'Math Helper',
        'Assistant',
        'Help users solve mathematical problems',
        [
            'backstory' => 'I am an AI assistant specialized in mathematics.',
            'tools' => ['calculator'],
        ]
    );
    echo "Agent '{$agent->getName()}' created successfully!\n";
    echo "Role: {$agent->getRole()}\n";
    echo "Goal: {$agent->getGoal()}\n";
    
    // Check if we have required API keys for LLM
    if (empty($apiKey)) {
        echo "\nWarning: OpenAI API key is not set. Running the agent will fail.\n";
        echo "Please set the OPENAI_API_KEY in your .env file or environment variables.\n";
        exit(1);
    }
    
    echo "\nWould you like to test the agent with a math question? (y/n): ";
    $answer = trim(fgets(STDIN));
    
    if (strtolower($answer) === 'y') {
        echo "\nEnter a math question (e.g., 'What is 25 * 3?'): ";
        $question = trim(fgets(STDIN));
        
        echo "\nProcessing...\n";
        $response = $agent->run($question);
        
        echo "\nResponse: " . $response->getFinalAnswer() . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDotEnv configuration example completed.\n";
echo "You can modify your .env file to change the configuration.\n";
echo "See .env.example for all available configuration options.\n"; 