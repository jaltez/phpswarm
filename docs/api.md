# PHPSwarm API Documentation

## Overview

PHPSwarm is a PHP framework for building AI agent applications, providing a robust foundation with powerful features like LLM integration, workflow orchestration, memory management, and a flexible tool system.

This document outlines the API for components that have been implemented according to the project roadmap.

## Core Components

### Agent System

#### `PhpSwarm\Agent\Agent`

The main agent class that handles task execution, tool usage, and memory integration.

```php
// Create an agent with the factory
$agent = PhpSwarmFactory::createAgent('my-agent', $llm, $memory);

// Execute a task
$response = $agent->execute('Analyze this data and provide insights');

// Use specific tools for a task
$response = $agent->withTools([$calculator, $webSearch])
                 ->execute('Calculate 235 * 18 and find information about quantum computing');
```

#### `PhpSwarm\Agent\AgentBuilder`

Fluent interface for creating agent instances with custom configurations.

```php
// Create an agent using the builder pattern
$agent = (new AgentBuilder())
    ->withName('research-agent')
    ->withLLM($openAI)
    ->withMemory($redisMemory)
    ->withTools([$calculator, $webSearch, $weatherTool])
    ->withSystemPrompt('You are a research assistant specialized in data analysis')
    ->build();
```

### LLM Connectors

#### `PhpSwarm\LLM\OpenAI\OpenAIConnector`

Connector for OpenAI models (GPT-3.5, GPT-4, etc.).

```php
// Create an OpenAI connector
$openAI = new OpenAIConnector([
    'api_key' => 'your-api-key',
    'model' => 'gpt-4',
    'temperature' => 0.7
]);

// Generate a completion
$response = $openAI->complete('Write a short poem about artificial intelligence');

// Chat completion
$response = $openAI->chat([
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => 'What is machine learning?']
]);

// Stream response
$openAI->streamChat([
    ['role' => 'user', 'content' => 'Explain quantum computing']
], function($chunk) {
    echo $chunk;
});
```

#### `PhpSwarm\LLM\Anthropic\AnthropicConnector`

Connector for Anthropic's Claude models.

```php
// Create an Anthropic connector
$anthropic = new AnthropicConnector([
    'api_key' => 'your-anthropic-key',
    'model' => 'claude-3-opus-20240229'
]);

// Generate a completion
$response = $anthropic->complete('Explain the theory of relativity');

// Chat completion
$response = $anthropic->chat([
    ['role' => 'user', 'content' => 'How do neural networks work?']
]);
```

### Tool System

#### `PhpSwarm\Tool\BaseTool`

Base class for all tools that provides common functionality.

```php
// All tools extend BaseTool and implement the required methods
class MyCustomTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            'my_tool',
            'Description of what my tool does'
        );
        
        $this->parametersSchema = [
            'parameter1' => [
                'type' => 'string',
                'description' => 'Description of parameter1',
                'required' => true,
            ],
            // Additional parameters...
        ];
        
        $this->addTag('custom');
        $this->addTag('example');
    }
    
    public function run(array $parameters = []): mixed
    {
        $this->validateParameters($parameters);
        
        // Tool implementation...
        
        return $result;
    }
}
```

#### `PhpSwarm\Tool\Calculator\CalculatorTool`

A tool for performing basic arithmetic calculations.

```php
// Create a calculator tool
$calculator = new CalculatorTool();

// Use the calculator
$result = $calculator->run([
    'expression' => '2 + 2 * 3'
]); // Returns 8
```

#### `PhpSwarm\Tool\WebSearch\WebSearchTool`

A tool for searching the web for information.

```php
// Create a web search tool
$webSearch = new WebSearchTool([
    'api_key' => 'your-search-api-key'
]);

// Perform a search
$results = $webSearch->run([
    'query' => 'global warming effects',
    'num_results' => 5
]);
```

#### `PhpSwarm\Tool\Weather\WeatherTool`

A tool for retrieving weather information.

```php
// Create a weather tool
$weatherTool = new WeatherTool([
    'api_key' => 'your-weather-api-key'
]);

// Get weather for a location
$forecast = $weatherTool->run([
    'location' => 'New York, NY',
    'units' => 'metric'
]);
```

#### `PhpSwarm\Tool\FileSystem\FileSystemTool`

A tool for interacting with the file system.

```php
// Create a file system tool
$fileSystem = new FileSystemTool([
    'base_path' => '/path/to/files'
]);

// Read a file
$content = $fileSystem->run([
    'action' => 'read',
    'path' => 'documents/report.txt'
]);

// Write to a file
$fileSystem->run([
    'action' => 'write',
    'path' => 'output/data.json',
    'content' => json_encode($data)
]);
```

#### `PhpSwarm\Tool\DatabaseQuery\MySQLQueryTool`

A tool for executing MySQL queries.

```php
// Create a MySQL query tool
$mysqlQuery = new MySQLQueryTool([
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'my_database',
    'username' => 'db_user',
    'password' => 'db_password'
]);

// Execute a SELECT query
$results = $mysqlQuery->run([
    'query' => 'SELECT * FROM users WHERE status = ?',
    'params' => ['active'],
    'fetch_mode' => 'all'
]);

// Execute an INSERT query
$result = $mysqlQuery->run([
    'query' => 'INSERT INTO logs (message, level) VALUES (?, ?)',
    'params' => ['User logged in', 'info']
]);
```

#### `PhpSwarm\Tool\PDFReader\PDFReaderTool`

A tool for extracting text content from PDF documents.

```php
// Create a PDF reader tool
$pdfReader = new PDFReaderTool();

// Extract text from a PDF
$text = $pdfReader->run([
    'file_path' => '/path/to/document.pdf',
    'format' => 'text'
]);

// Extract text with metadata in JSON format
$data = $pdfReader->run([
    'file_path' => '/path/to/document.pdf',
    'page' => 5, // Extract specific page
    'format' => 'json'
]);
```

### Memory Management

#### `PhpSwarm\Memory\ArrayMemory`

In-memory storage for non-persistent memory.

```php
// Create an array memory store
$memory = new ArrayMemory();

// Store data
$memory->store('conversation', [
    'role' => 'user',
    'content' => 'What is artificial intelligence?'
]);

// Retrieve data
$conversations = $memory->retrieve('conversation');

// Search memory
$results = $memory->search('artificial intelligence');
```

#### `PhpSwarm\Memory\RedisMemory`

Distributed persistent storage using Redis.

```php
// Create a Redis memory store
$memory = new RedisMemory([
    'host' => 'localhost',
    'port' => 6379,
    'prefix' => 'agent:'
]);

// Store data with TTL
$memory->store('session:12345', [
    'user_id' => 42,
    'last_action' => 'login'
], 3600); // TTL of 1 hour

// Retrieve data
$session = $memory->retrieve('session:12345');
```

#### `PhpSwarm\Memory\SqliteMemory`

File-based persistent storage using SQLite.

```php
// Create a SQLite memory store
$memory = new SqliteMemory([
    'database' => '/path/to/memory.sqlite'
]);

// Store data with metadata
$memory->store('document:report', 
    $documentText,
    [
        'author' => 'John Doe',
        'created_at' => '2023-06-15',
        'tags' => ['financial', 'quarterly']
    ]
);

// Search by metadata
$results = $memory->search('', [
    'tags' => 'financial',
    'created_at>' => '2023-01-01'
]);
```

### Configuration System

#### `PhpSwarm\Config\PhpSwarmConfig`

Centralized configuration manager that uses the "convention over configuration" principle.

```php
// Load configuration from .env
$config = new PhpSwarmConfig();

// Get a configuration value
$apiKey = $config->get('openai.api_key');

// Set a configuration value
$config->set('agent.default_model', 'gpt-4');

// Load configuration from a file
$config->loadFromFile('/path/to/config.php');
```

### Workflow Engine

#### `PhpSwarm\Workflow\Workflow`

Orchestrates multi-step workflows with dependency management.

```php
// Create a workflow
$workflow = new Workflow('data_analysis');

// Add steps to the workflow
$workflow->addStep(new AgentStep('extract', $dataExtractAgent, 'Extract data from the provided sources'))
         ->addStep(new AgentStep('transform', $transformAgent, 'Transform the extracted data'))
         ->addStep(new AgentStep('analyze', $analysisAgent, 'Analyze the transformed data'))
         ->addStep(new FunctionStep('save', function($results) {
             // Save the analysis results
             return saveResults($results);
         }));

// Define dependencies between steps
$workflow->setDependency('transform', 'extract')
         ->setDependency('analyze', 'transform')
         ->setDependency('save', 'analyze');

// Execute the workflow
$result = $workflow->execute([
    'sources' => ['data1.csv', 'data2.json']
]);
```

### Logging and Monitoring

#### `PhpSwarm\Logger\FileLogger`

File-based logger with PSR-3 style levels.

```php
// Create a file logger
$logger = new FileLogger('/path/to/logs/app.log');

// Log messages at different levels
$logger->info('User logged in', ['user_id' => 123]);
$logger->error('Failed to connect to API', ['api' => 'openai', 'error' => 'Timeout']);
```

#### `PhpSwarm\Monitor\PerformanceMonitor`

Tracks performance metrics for operations.

```php
// Create a performance monitor
$monitor = new PerformanceMonitor();

// Start timing an operation
$timer = $monitor->startTimer('api_request');

// ... perform operation ...

// Stop the timer and record
$duration = $monitor->stopTimer($timer);

// Record a counter
$monitor->incrementCounter('api_requests');

// Get performance metrics
$metrics = $monitor->getMetrics();
```

## Factory

### `PhpSwarm\Factory\PhpSwarmFactory`

Factory class for simplified component creation.

```php
// Configure the factory
PhpSwarmFactory::configure([
    'openai' => [
        'api_key' => 'your-api-key',
        'model' => 'gpt-4'
    ],
    'memory' => [
        'type' => 'redis',
        'host' => 'localhost',
        'port' => 6379
    ]
]);

// Create components using the factory
$llm = PhpSwarmFactory::createLLM('openai');
$memory = PhpSwarmFactory::createMemory('redis');
$agent = PhpSwarmFactory::createAgent('assistant', $llm, $memory);
$tool = PhpSwarmFactory::createTool('calculator');
```

## Utility Components

### `PhpSwarm\Utility\TokenCounter`

Counts tokens in text for LLM requests.

```php
// Count tokens for a text
$count = TokenCounter::count('This is a sample text');

// Count tokens for a chat message array
$count = TokenCounter::countMessages([
    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
    ['role' => 'user', 'content' => 'What is AI?']
]);
```

### `PhpSwarm\Utility\HttpClient`

Wrapper around Guzzle for HTTP requests.

```php
// Create an HTTP client
$client = new HttpClient();

// Make a GET request
$response = $client->get('https://api.example.com/data', [
    'headers' => ['Authorization' => 'Bearer token'],
    'query' => ['filter' => 'active']
]);

// Make a POST request
$response = $client->post('https://api.example.com/users', [
    'json' => [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]
]);
```

## CLI Tools

### `PhpSwarm\Console\Command\GenerateCommand`

Command for scaffolding new components.

```bash
# Generate a new agent
php bin/phpswarm generate:agent MyCustomAgent

# Generate a new tool
php bin/phpswarm generate:tool Weather

# Generate a new memory provider
php bin/phpswarm generate:memory FileSystemMemory

# Generate a new workflow
php bin/phpswarm generate:workflow DataProcessing
```

## Error Handling

### `PhpSwarm\Exception\Tool\ToolExecutionException`

Exception thrown when a tool execution fails.

```php
try {
    $result = $tool->run($parameters);
} catch (ToolExecutionException $e) {
    echo "Tool execution failed: " . $e->getMessage();
    echo "Parameters: " . json_encode($e->getParameters());
    echo "Tool: " . $e->getToolName();
}
```

This API documentation reflects the current state of the framework according to the PROGRESS.md file. As development continues, this documentation will be expanded to include new features and components. 