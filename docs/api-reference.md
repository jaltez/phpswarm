# PHPSwarm API Reference

This document provides comprehensive API documentation for PHPSwarm components, interfaces, and classes.

## Table of Contents

- [Core Factory](#core-factory)
- [Agent System](#agent-system)
- [LLM Connectors](#llm-connectors)
- [Tools System](#tools-system)
- [Memory Management](#memory-management)
- [Validation System](#validation-system)
- [Workflow Engine](#workflow-engine)
- [Swarm Coordination](#swarm-coordination)
- [Utility Components](#utility-components)
- [Configuration](#configuration)
- [Exceptions](#exceptions)

## Core Factory

### PhpSwarmFactory

The main factory class for creating PHPSwarm components.

```php
namespace PhpSwarm\Factory;

class PhpSwarmFactory
{
    public function __construct(?PhpSwarmConfig $config = null)
    public function getConfig(): PhpSwarmConfig

    // LLM Management
    public function createLLM(array $options = []): LLMInterface

    // Memory Management
    public function createMemory(array $options = []): MemoryInterface

    // Agent Management
    public function createAgent(string $name, string $role, string $goal, array $options = []): Agent
    public function createAgentBuilder(array $options = []): AgentBuilder

    // Tools Management
    public function createTool(string $name, array $options = []): ToolInterface

    // Workflow Management
    public function createWorkflow(string $name, string $description = '', array $options = []): WorkflowInterface
    public function createAgentStep(string $name, string $task, string $description = '', ?AgentInterface $agent = null): WorkflowStepInterface
    public function createFunctionStep(string $name, callable $function, string $description = ''): WorkflowStepInterface

    // Prompt Management
    public function createPromptManager(array $options = []): PromptManagerInterface
    public function createPromptTemplate(string $name, string $description, string $content, array $options = []): PromptTemplateInterface
    public function createPrompt(string $name, string $description, string $template, array $variables = [], array $options = []): PromptInterface

    // Logging and Monitoring
    public function createLogger(array $options = []): LoggerInterface
    public function createMonitor(array $options = []): MonitorInterface

    // Validation
    public function createValidator(array $options = []): ValidatorInterface
    public function createSchemaValidator(): SchemaValidator

    // Utility
    public function createTokenCounter(string $provider = 'generic'): TokenCounterInterface
    public function createTokenCounterForModel(string $model): TokenCounterInterface
    public function createBestTokenCounter(): TokenCounterInterface
}
```

## Agent System

### AgentInterface

```php
namespace PhpSwarm\Contract\Agent;

interface AgentInterface
{
    public function getName(): string
    public function getRole(): string
    public function getGoal(): string
    public function getInstructions(): string
    public function setInstructions(string $instructions): self
    public function addTool(ToolInterface $tool): self
    public function getTools(): array
    public function setMemory(MemoryInterface $memory): self
    public function getMemory(): ?MemoryInterface
    public function setLLM(LLMInterface $llm): self
    public function getLLM(): ?LLMInterface
    public function executeTask(string $task, array $context = []): mixed
    public function think(string $input, array $context = []): string
    public function remember(string $key, mixed $value, ?int $ttl = null): void
    public function recall(string $key): mixed
    public function addContext(string $key, mixed $value): self
    public function getContext(): array
}
```

### Agent

Main implementation of the agent system.

```php
namespace PhpSwarm\Agent;

class Agent implements AgentInterface
{
    public function __construct(
        string $name,
        string $role,
        string $goal,
        ?LLMInterface $llm = null,
        ?MemoryInterface $memory = null,
        array $tools = []
    )

    // Implementation of AgentInterface methods
    // Additional agent-specific functionality
}
```

### AgentBuilder

Fluent interface for building agents.

```php
namespace PhpSwarm\Agent;

class AgentBuilder
{
    public function name(string $name): self
    public function role(string $role): self
    public function goal(string $goal): self
    public function instructions(string $instructions): self
    public function llm(LLMInterface $llm): self
    public function memory(MemoryInterface $memory): self
    public function tool(ToolInterface $tool): self
    public function tools(array $tools): self
    public function context(string $key, mixed $value): self
    public function build(): Agent
}
```

## LLM Connectors

### LLMInterface

```php
namespace PhpSwarm\Contract\LLM;

interface LLMInterface
{
    public function generateResponse(string $prompt, array $options = []): LLMResponse
    public function generateResponseWithTools(string $prompt, array $tools = [], array $options = []): LLMResponse
    public function streamResponse(string $prompt, array $options = []): Generator
    public function chat(array $messages, array $options = []): LLMResponse
    public function chatWithTools(array $messages, array $tools = [], array $options = []): LLMResponse
    public function getModel(): string
    public function setModel(string $model): self
    public function getMaxTokens(): ?int
    public function setMaxTokens(?int $maxTokens): self
    public function getTemperature(): float
    public function setTemperature(float $temperature): self
    public function isAvailable(): bool
}
```

### LLMResponse

```php
namespace PhpSwarm\Contract\LLM;

interface LLMResponse
{
    public function getContent(): string
    public function getTokenUsage(): array
    public function getModel(): string
    public function getFinishReason(): ?string
    public function getToolCalls(): array
    public function hasToolCalls(): bool
    public function getMetadata(): array
}
```

### OpenAIConnector

```php
namespace PhpSwarm\LLM\OpenAI;

class OpenAIConnector implements LLMInterface
{
    public function __construct(array $config = [])

    // Implementation of LLMInterface methods
    // OpenAI-specific functionality
}
```

### AnthropicConnector

```php
namespace PhpSwarm\LLM\Anthropic;

class AnthropicConnector implements LLMInterface
{
    public function __construct(array $config = [])

    // Implementation of LLMInterface methods
    // Anthropic-specific functionality
}
```

## Tools System

### ToolInterface

```php
namespace PhpSwarm\Contract\Tool;

interface ToolInterface
{
    public function run(array $parameters = []): mixed
    public function getName(): string
    public function getDescription(): string
    public function getParametersSchema(): array
    public function isAvailable(): bool
    public function requiresAuthentication(): bool
    public function addTag(string $tag): self
    public function getTags(): array
}
```

### BaseTool

Base implementation for tools.

```php
namespace PhpSwarm\Tool;

abstract class BaseTool implements ToolInterface
{
    protected array $parametersSchema = []
    protected array $tags = []
    protected bool $requiresAuth = false

    public function __construct(protected string $name, protected string $description)

    protected function validateParameters(array $parameters): void
    protected function validateType(mixed $value, string $type): bool
    public function setRequiresAuthentication(bool $requiresAuth): self
}
```

### Available Tools

#### CalculatorTool

```php
namespace PhpSwarm\Tool\Calculator;

class CalculatorTool extends BaseTool
{
    public function run(array $parameters = []): mixed
    // Performs mathematical calculations
    // Parameters: expression (string)
}
```

#### WebSearchTool

```php
namespace PhpSwarm\Tool\WebSearch;

class WebSearchTool extends BaseTool
{
    public function __construct(array $config = [])
    public function run(array $parameters = []): mixed
    // Performs web searches
    // Parameters: query (string), num_results (int)
}
```

#### WeatherTool

```php
namespace PhpSwarm\Tool\Weather;

class WeatherTool extends BaseTool
{
    public function __construct(array $config = [])
    public function run(array $parameters = []): mixed
    // Gets weather information
    // Parameters: location (string), type (string), days (int)
}
```

#### FileSystemTool

```php
namespace PhpSwarm\Tool\FileSystem;

class FileSystemTool extends BaseTool
{
    public function __construct(array $config = [])
    public function run(array $parameters = []): mixed
    // File system operations
    // Parameters: operation (string), path (string), content (string), recursive (bool)
}
```

#### MySQLQueryTool

```php
namespace PhpSwarm\Tool\DatabaseQuery;

class MySQLQueryTool extends BaseTool
{
    public function __construct(array $config = [])
    public function run(array $parameters = []): mixed
    // Executes MySQL queries
    // Parameters: query (string), params (array), fetch_mode (string)
}
```

#### PDFReaderTool

```php
namespace PhpSwarm\Tool\PDFReader;

class PDFReaderTool extends BaseTool
{
    public function __construct(?string $pdftotextPath = null)
    public function run(array $parameters = []): mixed
    // Extracts text from PDF files
    // Parameters: file_path (string), page (int), format (string)
}
```

## Memory Management

### MemoryInterface

```php
namespace PhpSwarm\Contract\Memory;

interface MemoryInterface
{
    public function store(string $key, mixed $value, ?int $ttl = null): void
    public function retrieve(string $key): mixed
    public function exists(string $key): bool
    public function delete(string $key): bool
    public function clear(): void
    public function keys(string $pattern = '*'): array
    public function getMetadata(string $key): ?array
    public function getHistory(string $key, int $limit = 10): array
    public function search(string $query, int $limit = 10): array
}
```

### ArrayMemory

```php
namespace PhpSwarm\Memory;

class ArrayMemory implements MemoryInterface
{
    // In-memory storage implementation
    // Non-persistent, good for testing and simple applications
}
```

### RedisMemory

```php
namespace PhpSwarm\Memory;

class RedisMemory implements MemoryInterface
{
    public function __construct(array $config = [])

    // Redis-based persistent storage
    // Supports TTL, metadata, history, and search
}
```

### SqliteMemory

```php
namespace PhpSwarm\Memory;

class SqliteMemory implements MemoryInterface
{
    public function __construct(array $config = [])

    // SQLite-based file storage
    // Lightweight persistent storage option
}
```

## Validation System

### ValidatorInterface

```php
namespace PhpSwarm\Contract\Utility;

interface ValidatorInterface
{
    public function validate(mixed $data, array $schema): ValidationResult
    public function validateOrThrow(mixed $data, array $schema): void
    public function validateObject(object $object): ValidationResult
    public function addRule(string $name, callable $validator): void
    public function hasRule(string $name): bool
}
```

### ValidationResult

```php
namespace PhpSwarm\Contract\Utility;

class ValidationResult
{
    public function __construct(
        private readonly bool $isValid,
        private readonly array $errors = [],
        private readonly array $validatedData = []
    )

    public function isValid(): bool
    public function isInvalid(): bool
    public function getErrors(): array
    public function getFieldErrors(string $field): array
    public function hasFieldErrors(string $field): bool
    public function getAllErrorMessages(): array
    public function getFirstError(): ?string
    public function getValidatedData(): array
    public function getValidatedField(string $field, mixed $default = null): mixed

    public static function success(array $validatedData = []): self
    public static function failure(array $errors): self
}
```

### Validator

```php
namespace PhpSwarm\Utility\Validation;

class Validator implements ValidatorInterface
{
    // Main validator implementation
    // Supports schema validation, attribute validation, and custom rules
}
```

### SchemaValidator

```php
namespace PhpSwarm\Utility\Validation;

class SchemaValidator
{
    public function validateSchema(mixed $data, array $schema, string $path = ''): ValidationResult
    // JSON Schema-like validation for complex nested structures
}
```

### Validation Attributes

```php
namespace PhpSwarm\Utility\Validation\Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Required
{
    public function __construct(public readonly ?string $message = null)
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Type
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $message = null
    )
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Length
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?int $exact = null,
        public readonly ?string $message = null
    )
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Range
{
    public function __construct(
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
        public readonly ?string $message = null
    )
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Pattern
{
    public function __construct(
        public readonly string $pattern,
        public readonly ?string $message = null
    )
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class InArray
{
    public function __construct(
        public readonly array $values,
        public readonly bool $strict = true,
        public readonly ?string $message = null
    )
}
```

## Workflow Engine

### WorkflowInterface

```php
namespace PhpSwarm\Contract\Workflow;

interface WorkflowInterface
{
    public function getName(): string
    public function getDescription(): string
    public function addStep(WorkflowStepInterface $step): self
    public function removeStep(string $stepName): self
    public function getStep(string $stepName): ?WorkflowStepInterface
    public function getSteps(): array
    public function addDependency(string $stepName, string $dependsOn): self
    public function execute(array $context = []): WorkflowResult
    public function canRunInParallel(): bool
    public function setMaxParallelSteps(int $maxSteps): self
    public function getMaxParallelSteps(): int
}
```

### WorkflowStepInterface

```php
namespace PhpSwarm\Contract\Workflow;

interface WorkflowStepInterface
{
    public function getName(): string
    public function getDescription(): string
    public function execute(array $context = []): WorkflowStepResult
    public function getDependencies(): array
    public function addDependency(string $stepName): self
    public function canRunInParallel(): bool
    public function getTimeout(): ?int
    public function setTimeout(?int $timeout): self
}
```

### WorkflowResult

```php
namespace PhpSwarm\Contract\Workflow;

interface WorkflowResult
{
    public function isSuccessful(): bool
    public function getStepResults(): array
    public function getStepResult(string $stepName): ?WorkflowStepResult
    public function getFailedSteps(): array
    public function getExecutionTime(): float
    public function getContext(): array
    public function getErrors(): array
}
```

## Swarm Coordination

### SwarmInterface

```php
namespace PhpSwarm\Contract\Swarm;

interface SwarmInterface
{
    public function addAgent(AgentInterface $agent): self
    public function removeAgent(string $agentName): self
    public function getAgent(string $agentName): ?AgentInterface
    public function getAgents(): array
    public function setCoordinator(SwarmCoordinatorInterface $coordinator): self
    public function getCoordinator(): ?SwarmCoordinatorInterface
    public function executeTask(string $task, array $context = []): SwarmResult
    public function broadcastMessage(MessageInterface $message): void
    public function sendMessage(string $recipientName, MessageInterface $message): void
}
```

### SwarmCoordinatorInterface

```php
namespace PhpSwarm\Contract\Swarm;

interface SwarmCoordinatorInterface
{
    public function coordinate(SwarmInterface $swarm, string $task, array $context = []): SwarmResult
    public function assignTask(AgentInterface $agent, string $task, array $context = []): mixed
    public function handleMessage(MessageInterface $message, SwarmInterface $swarm): void
    public function getStrategy(): string
}
```

### MessageInterface

```php
namespace PhpSwarm\Contract\Message;

interface MessageInterface
{
    public function getId(): string
    public function getSender(): string
    public function getRecipient(): ?string
    public function getContent(): string
    public function getType(): string
    public function getMetadata(): array
    public function getTimestamp(): DateTimeInterface
    public function addMetadata(string $key, mixed $value): self
}
```

## Utility Components

### TokenCounterInterface

```php
namespace PhpSwarm\Contract\Utility;

interface TokenCounterInterface
{
    public function count(string $text): int
    public function countMessages(array $messages): int
    public function getModel(): string
    public function getEncoding(): string
    public function truncate(string $text, int $maxTokens): string
    public function truncateMessages(array $messages, int $maxTokens): array
    public function estimateCost(int $tokens, string $type = 'input'): float
}
```

### LoggerInterface

```php
namespace PhpSwarm\Contract\Logger;

interface LoggerInterface
{
    public function emergency(string $message, array $context = []): void
    public function alert(string $message, array $context = []): void
    public function critical(string $message, array $context = []): void
    public function error(string $message, array $context = []): void
    public function warning(string $message, array $context = []): void
    public function notice(string $message, array $context = []): void
    public function info(string $message, array $context = []): void
    public function debug(string $message, array $context = []): void
    public function log(string $level, string $message, array $context = []): void
    public function setLevel(string $level): self
    public function getLevel(): string
}
```

### MonitorInterface

```php
namespace PhpSwarm\Contract\Logger;

interface MonitorInterface
{
    public function startTimer(string $name): void
    public function endTimer(string $name): float
    public function recordMetric(string $name, mixed $value, array $tags = []): void
    public function incrementCounter(string $name, array $tags = []): void
    public function trackProcess(string $name, callable $process): mixed
    public function getMetrics(): array
    public function getTimers(): array
    public function getCounters(): array
    public function reset(): void
}
```

## Configuration

### PhpSwarmConfig

```php
namespace PhpSwarm\Config;

class PhpSwarmConfig
{
    public static function getInstance(): self
    public static function fromArray(array $config): self
    public static function fromFile(string $filepath): self
    public static function fromEnv(string $prefix = 'PHPSWARM_'): self

    public function get(string $key, mixed $default = null): mixed
    public function set(string $key, mixed $value): self
    public function has(string $key): bool
    public function remove(string $key): self
    public function all(): array
    public function merge(array $config): self
    public function getSection(string $section): array
}
```

## Exceptions

### PhpSwarmException

Base exception for all PHPSwarm exceptions.

```php
namespace PhpSwarm\Exception;

class PhpSwarmException extends Exception
{
    // Base exception class
}
```

### Specific Exceptions

```php
// Agent Exceptions
namespace PhpSwarm\Exception\Agent;
class AgentException extends PhpSwarmException
class AgentExecutionException extends AgentException

// LLM Exceptions
namespace PhpSwarm\Exception\LLM;
class LLMException extends PhpSwarmException
class LLMConnectionException extends LLMException
class LLMResponseException extends LLMException

// Tool Exceptions
namespace PhpSwarm\Exception\Tool;
class ToolException extends PhpSwarmException
class ToolExecutionException extends ToolException

// Memory Exceptions
namespace PhpSwarm\Exception\Memory;
class MemoryException extends PhpSwarmException

// Validation Exceptions
namespace PhpSwarm\Exception\Utility;
class ValidationException extends PhpSwarmException

// Workflow Exceptions
namespace PhpSwarm\Exception\Workflow;
class WorkflowException extends PhpSwarmException
class WorkflowStepException extends WorkflowException

// Swarm Exceptions
namespace PhpSwarm\Exception\Swarm;
class SwarmException extends PhpSwarmException
class SwarmCoordinationException extends SwarmException
```

## Usage Examples

### Basic Agent Creation

```php
use PhpSwarm\Factory\PhpSwarmFactory;

$factory = new PhpSwarmFactory();

// Create LLM and memory
$llm = $factory->createLLM(['provider' => 'openai']);
$memory = $factory->createMemory(['provider' => 'array']);

// Create tools
$calculator = $factory->createTool('calculator');
$webSearch = $factory->createTool('web_search', [
    'api_key' => 'your-api-key'
]);

// Create agent
$agent = $factory->createAgent(
    name: 'Research Assistant',
    role: 'AI Research Assistant',
    goal: 'Help users find and analyze information'
);

$agent->setLLM($llm)
      ->setMemory($memory)
      ->addTool($calculator)
      ->addTool($webSearch);

// Execute task
$result = $agent->executeTask('Find the latest information about AI trends');
```

### Workflow Creation

```php
$workflow = $factory->createWorkflow('Data Analysis', 'Analyze data from multiple sources');

// Add steps
$dataStep = $factory->createAgentStep('collect_data', 'Collect data from sources', '', $dataAgent);
$analyzeStep = $factory->createAgentStep('analyze_data', 'Analyze collected data', '', $analysisAgent);
$reportStep = $factory->createFunctionStep('generate_report', function($context) {
    return "Generated report from: " . json_encode($context);
}, 'Generate final report');

$workflow->addStep($dataStep)
         ->addStep($analyzeStep)
         ->addStep($reportStep);

// Add dependencies
$workflow->addDependency('analyze_data', 'collect_data')
         ->addDependency('generate_report', 'analyze_data');

// Execute workflow
$result = $workflow->execute(['source' => 'api']);
```

### Validation Usage

```php
$validator = $factory->createValidator();

$schema = [
    'name' => ['type' => 'string', 'required' => true, 'min_length' => 2],
    'email' => ['type' => 'string', 'required' => true, 'pattern' => '/^.+@.+\..+$/'],
    'age' => ['type' => 'int', 'min' => 18, 'max' => 120]
];

$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
];

$result = $validator->validate($data, $schema);
if ($result->isValid()) {
    $validatedData = $result->getValidatedData();
    // Use validated data
}
```

This API reference provides comprehensive documentation for all major PHPSwarm components and their interfaces. Each component includes method signatures, parameter descriptions, and usage examples to help developers effectively use the framework.
