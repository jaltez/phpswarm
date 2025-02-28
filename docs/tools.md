# PHPSwarm Tools

This document describes the tools available in PHPSwarm and how to use them.

## Introduction to Tools

Tools in PHPSwarm are capabilities that agents can use to interact with external systems or perform specific tasks. Each tool has a defined interface, accepts parameters, and returns results that the agent can use.

## Available Tools

PHPSwarm comes with several built-in tools:

### Calculator Tool

The Calculator Tool performs basic arithmetic calculations.

```php
$calculator = new \PhpSwarm\Tool\Calculator\CalculatorTool();
$result = $calculator->run([
    'expression' => '2 + 2 * 3'
]); // Returns 8
```

### Web Search Tool

The Web Search Tool searches the web for information.

```php
$webSearch = new \PhpSwarm\Tool\WebSearch\WebSearchTool([
    'api_key' => 'your-search-api-key'
]);
$results = $webSearch->run([
    'query' => 'global warming effects',
    'num_results' => 5
]);
```

### Weather Tool

The Weather Tool retrieves weather information for a location.

```php
$weatherTool = new \PhpSwarm\Tool\Weather\WeatherTool([
    'api_key' => 'your-weather-api-key'
]);
$forecast = $weatherTool->run([
    'location' => 'New York, NY',
    'units' => 'metric'
]);
```

### File System Tool

The File System Tool interacts with the file system to read, write, and manipulate files.

```php
$fileSystem = new \PhpSwarm\Tool\FileSystem\FileSystemTool([
    'base_path' => '/path/to/files'
]);
$content = $fileSystem->run([
    'action' => 'read',
    'path' => 'documents/report.txt'
]);
```

### MySQL Query Tool

The MySQL Query Tool executes SQL queries against a MySQL database.

```php
$mysqlQuery = new \PhpSwarm\Tool\DatabaseQuery\MySQLQueryTool([
    'host' => 'localhost',
    'database' => 'my_database',
    'username' => 'db_user',
    'password' => 'db_password'
]);
$results = $mysqlQuery->run([
    'query' => 'SELECT * FROM users WHERE status = ?',
    'params' => ['active']
]);
```

### PDF Reader Tool

The PDF Reader Tool extracts text content from PDF documents.

```php
$pdfReader = new \PhpSwarm\Tool\PDFReader\PDFReaderTool();
$text = $pdfReader->run([
    'file_path' => '/path/to/document.pdf',
    'format' => 'text'
]);
```

## Creating Custom Tools

To create a custom tool, extend the `BaseTool` class and implement the `run` method:

```php
namespace MyApp\Tool;

use PhpSwarm\Tool\BaseTool;
use PhpSwarm\Exception\Tool\ToolExecutionException;

class MyCustomTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            'my_custom_tool',
            'Description of what my tool does'
        );
        
        $this->parametersSchema = [
            'parameter1' => [
                'type' => 'string',
                'description' => 'Description of parameter1',
                'required' => true,
            ],
            'parameter2' => [
                'type' => 'integer',
                'description' => 'Description of parameter2',
                'required' => false,
                'default' => 42,
            ],
        ];
        
        $this->addTag('custom');
        $this->addTag('example');
    }
    
    public function run(array $parameters = []): mixed
    {
        $this->validateParameters($parameters);
        
        $param1 = $parameters['parameter1'];
        $param2 = $parameters['parameter2'] ?? 42;
        
        try {
            // Implement your tool's functionality here
            $result = $this->processData($param1, $param2);
            return $result;
        } catch (\Throwable $e) {
            throw new ToolExecutionException(
                "Failed to execute tool: {$e->getMessage()}",
                $parameters,
                $this->getName(),
                0,
                $e
            );
        }
    }
    
    private function processData(string $param1, int $param2): mixed
    {
        // Your custom implementation
        return "Processed: {$param1} with value {$param2}";
    }
}
```

## Using Tools with Agents

Tools can be provided to agents to give them additional capabilities:

```php
$agent = (new AgentBuilder())
    ->withName('research-agent')
    ->withLLM($openAI)
    ->withMemory($memory)
    ->withTools([
        new CalculatorTool(),
        new WebSearchTool(['api_key' => $searchApiKey]),
        new WeatherTool(['api_key' => $weatherApiKey]),
        new MyCustomTool()
    ])
    ->build();

$response = $agent->execute(
    "Calculate 235 * 18, search for information about quantum computing, " .
    "and check the weather in London."
);
```

## Tool Authorization

Tools can require authorization to use sensitive resources or APIs:

```php
class SecureTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct('secure_tool', 'A tool requiring authorization');
        $this->setRequiresAuthentication(true);
        // ...
    }
    
    // ...
}
```

When a tool requires authentication, the agent will check if it has the necessary credentials before using the tool. 