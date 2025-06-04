#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Agent\Agent;
use PhpSwarm\LLM\Ollama\OllamaConnector;
use PhpSwarm\Tool\WebSearch\WebSearchTool;
use PhpSwarm\Memory\ArrayMemory;

// Create an Ollama connector with the default llama3 model
$llm = new OllamaConnector([
    // 'model' => 'llama3.2:latest',
    'model' => 'gemma3:1b',
    'temperature' => 0.7,
    'base_url' => 'http://localhost:11434', // Adjust if your Ollama server is running elsewhere
]);

// Check if search API keys are available
$searchApiKey = getenv('SEARCH_API_KEY');
$searchEngineId = getenv('SEARCH_ENGINE_ID');
$useWebSearch = !empty($searchApiKey) && !empty($searchEngineId);

// Create a memory system
$memory = new ArrayMemory();

// Create an agent builder
$agentBuilder = Agent::create()
    ->withName('News Reporter')
    ->withRole('Assistant')
    ->withGoal('Share engaging news stories with a flair for storytelling')
    ->withBackstory('I am an enthusiastic news reporter with a flair for storytelling!')
    ->withLLM($llm)
    ->withMemory($memory)
    ->withVerboseLogging(true)
    ->withColorizedOutput();

// Add web search tool only if API keys are available
if ($useWebSearch) {
    // Create a web search tool for retrieving real-time information
    $webSearchTool = new WebSearchTool([
        'api_key' => $searchApiKey,
        'search_engine_id' => $searchEngineId,
        'service' => 'google'
    ]);
    
    $agentBuilder->addTool($webSearchTool);
    echo "Web search tool enabled.\n";
} else {
    echo "Web search tool disabled due to missing API keys.\n";
    echo "The agent will rely on its built-in knowledge only.\n";
}

// Build the agent
$agent = $agentBuilder->build();

// Function to handle the news query and display the response
function getNewsStory(Agent $agent, string $query) {
    echo "Searching for news...\n\n";
    
    // Run the agent with the user's query
    $startTime = microtime(true);
    
    // Stream the response
    $response = $agent->run($query, [
        'stream' => true,
        'streamCallback' => function($chunk) {
            // For Ollama, the response format is different from OpenAI
            if (isset($chunk['message']['content'])) {
                echo $chunk['message']['content'];
                flush();
            } elseif (isset($chunk['response'])) {
                echo $chunk['response'];
                flush();
            }
        }
    ]);
    
    $endTime = microtime(true);
    
    echo "\n\n--- Stats ---\n";
    echo "Processing time: " . round(($endTime - $startTime), 2) . " seconds\n";
    
    // Display token usage information if available
    $tokenUsage = $response->getTokenUsage();
    
    if (!empty($tokenUsage)) {
        if (isset($tokenUsage['prompt_tokens'])) {
            echo "Prompt tokens: " . $tokenUsage['prompt_tokens'] . "\n";
        }
        
        if (isset($tokenUsage['completion_tokens'])) {
            echo "Completion tokens: " . $tokenUsage['completion_tokens'] . "\n";
        }
        
        if (isset($tokenUsage['total_tokens'])) {
            echo "Total tokens: " . $tokenUsage['total_tokens'] . "\n";
        }
    }
}

// Display Ollama usage instructions
echo "\n=== Ollama Usage Instructions ===\n";
echo "1. Make sure Ollama is installed and running on your machine (or specified host).\n";
echo "   Download from: https://ollama.com/download\n";
echo "2. Make sure you have pulled the model this example uses:\n";
echo "   Run: ollama pull llama3\n";
echo "3. If you want to use a different model, update the 'model' in the OllamaConnector.\n";
echo "=================================\n\n";

// Run the news reporter agent with the query from the Python example
getNewsStory($agent, "Share a news story from New York."); 