<?php

declare(strict_types=1);

namespace PhpSwarm\Factory;

use PhpSwarm\Agent\Agent;
use PhpSwarm\Agent\AgentBuilder;
use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\Memory\MemoryInterface;
use PhpSwarm\Contract\Tool\ToolInterface;
use PhpSwarm\Exception\PhpSwarmException;
use PhpSwarm\LLM\OpenAI\OpenAIConnector;
use PhpSwarm\Memory\ArrayMemory;
use PhpSwarm\Tool\Calculator\CalculatorTool;
use PhpSwarm\Tool\Weather\WeatherTool;
use PhpSwarm\Tool\WebSearch\WebSearchTool;
use PhpSwarm\Tool\FileSystem\FileSystemTool;
use PhpSwarm\Contract\Prompt\PromptInterface;
use PhpSwarm\Contract\Prompt\PromptManagerInterface;
use PhpSwarm\Contract\Prompt\PromptTemplateInterface;
use PhpSwarm\Prompt\BasePrompt;
use PhpSwarm\Prompt\PromptManager;
use PhpSwarm\Prompt\PromptTemplate;
use PhpSwarm\Contract\Utility\TokenCounterInterface;
use PhpSwarm\Contract\Utility\ValidatorInterface;
use PhpSwarm\Utility\TokenCounter\TokenCounterFactory;
use PhpSwarm\Utility\Validation\Validator;
use PhpSwarm\Utility\Validation\SchemaValidator;
use PhpSwarm\Contract\Utility\EventDispatcherInterface;
use PhpSwarm\Contract\Utility\EventInterface;
use PhpSwarm\Utility\Event\EventDispatcher;
use PhpSwarm\Utility\Event\Event;
use PhpSwarm\Utility\Event\LoggingEventSubscriber;

/**
 * Factory class for creating PHPSwarm components.
 */
class PhpSwarmFactory
{
    /**
     * @var PhpSwarmConfig The configuration instance
     */
    private readonly PhpSwarmConfig $config;

    /**
     * @var array<string, object> Registry of created objects
     */
    private array $registry = [];

    /**
     * Create a new PhpSwarmFactory instance.
     *
     * @param PhpSwarmConfig|null $config The configuration instance
     */
    public function __construct(?PhpSwarmConfig $config = null)
    {
        $this->config = $config ?? PhpSwarmConfig::getInstance();
    }

    /**
     * Create an LLM connector based on the configured provider.
     *
     * @param array<string, mixed> $options Additional options to override configuration
     * @return LLMInterface The LLM connector
     * @throws PhpSwarmException If the provider is not supported
     */
    public function createLLM(array $options = []): LLMInterface
    {
        $provider = $options['provider'] ?? $this->config->get('llm.provider', 'openai');

        return match ($provider) {
            'openai' => $this->createOpenAIConnector($options),
            'anthropic' => $this->createAnthropicConnector($options),
            default => throw new PhpSwarmException("Unsupported LLM provider: $provider"),
        };
    }

    /**
     * Create an OpenAI LLM connector.
     *
     * @param array<string, mixed> $options Additional options to override configuration
     * @return LLMInterface The OpenAI connector
     */
    private function createOpenAIConnector(array $options = []): LLMInterface
    {
        $config = [
            'api_key' => $options['api_key'] ?? $this->config->get('llm.openai.api_key', ''),
            'model' => $options['model'] ?? $this->config->get('llm.model', 'gpt-4'),
            'base_url' => $options['base_url'] ?? $this->config->get('llm.openai.base_url', 'https://api.openai.com/v1'),
            'temperature' => $options['temperature'] ?? $this->config->get('llm.temperature', 0.7),
            'max_tokens' => $options['max_tokens'] ?? $this->config->get('llm.max_tokens', null),
            'top_p' => $options['top_p'] ?? $this->config->get('llm.top_p', 1.0),
            'frequency_penalty' => $options['frequency_penalty'] ?? $this->config->get('llm.frequency_penalty', 0.0),
            'presence_penalty' => $options['presence_penalty'] ?? $this->config->get('llm.presence_penalty', 0.0),
        ];

        return new OpenAIConnector($config);
    }

    /**
     * Create an Anthropic connector.
     *
     * @param array<string, mixed> $options
     */
    private function createAnthropicConnector(array $options = []): LLMInterface
    {
        // Get API key from config or options
        $apiKey = $options['api_key'] ?? $this->config->get('llm.anthropic.api_key');

        // Get other options
        $model = $options['model'] ?? $this->config->get('llm.anthropic.model', 'claude-3-sonnet-20240229');
        $temperature = $options['temperature'] ?? $this->config->get('llm.temperature', 0.7);
        $maxTokens = $options['max_tokens'] ?? $this->config->get('llm.max_tokens', 4096);

        $connector = new \PhpSwarm\LLM\Anthropic\AnthropicConnector([
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'base_url' => $options['base_url'] ?? $this->config->get('llm.anthropic.base_url', 'https://api.anthropic.com'),
        ]);

        // Store in registry
        $this->registry['llm_anthropic'] = $connector;

        return $connector;
    }

    /**
     * Create a memory instance based on the configured provider.
     *
     * @param array<string, mixed> $options
     * @throws PhpSwarmException If the memory provider is not supported
     */
    public function createMemory(array $options = []): MemoryInterface
    {
        $provider = $options['provider'] ?? $this->config->get('memory.provider', 'array');

        return match ($provider) {
            'array' => $this->createArrayMemory(),
            'redis' => $this->createRedisMemory($options),
            'sqlite' => $this->createSqliteMemory($options),
            'vector' => $this->createVectorMemory($options),
            default => throw new PhpSwarmException("Unsupported memory provider: $provider"),
        };
    }

    /**
     * Create an array memory instance.
     */
    private function createArrayMemory(): MemoryInterface
    {
        $memory = new ArrayMemory();
        // Store in registry
        $this->registry['memory_array'] = $memory;
        return $memory;
    }

    /**
     * Create a Redis memory instance.
     *
     * @param array<string, mixed> $options
     */
    private function createRedisMemory(array $options = []): MemoryInterface
    {
        // Get Redis configuration
        $host = $options['host'] ?? $this->config->get('memory.redis.host', 'localhost');
        $port = $options['port'] ?? $this->config->get('memory.redis.port', 6379);
        $database = $options['database'] ?? $this->config->get('memory.redis.database', 0);
        $password = $options['password'] ?? $this->config->get('memory.redis.password');
        $prefix = $options['prefix'] ?? $this->config->get('memory.redis.prefix', 'phpswarm:');
        $ttl = $options['ttl'] ?? $this->config->get('memory.ttl', 3600);

        $memory = new \PhpSwarm\Memory\RedisMemory([
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'password' => $password,
            'prefix' => $prefix,
            'ttl' => $ttl,
        ]);

        // Store in registry
        $this->registry['memory_redis'] = $memory;

        return $memory;
    }

    /**
     * Create a SQLite memory instance.
     *
     * @param array<string, mixed> $options
     */
    private function createSqliteMemory(array $options = []): MemoryInterface
    {
        // Get SQLite configuration
        $dbPath = $options['db_path'] ?? $this->config->get('memory.sqlite.db_path', 'storage/memory.sqlite');
        $tableName = $options['table_name'] ?? $this->config->get('memory.sqlite.table_name', 'memory');
        $ttl = $options['ttl'] ?? $this->config->get('memory.ttl', 3600);

        // Ensure the directory exists
        $directory = dirname((string) $dbPath);
        if (!file_exists($directory) && $dbPath !== ':memory:') {
            mkdir($directory, 0755, true);
        }

        $memory = new \PhpSwarm\Memory\SqliteMemory([
            'db_path' => $dbPath,
            'table_name' => $tableName,
            'ttl' => $ttl,
        ]);

        // Store in registry
        $this->registry['memory_sqlite'] = $memory;

        return $memory;
    }

    /**
     * Create a vector memory instance.
     *
     * @param array<string, mixed> $options
     * @throws PhpSwarmException If required dependencies are missing
     */
    private function createVectorMemory(array $options = []): \PhpSwarm\Contract\Memory\VectorMemoryInterface
    {
        // Create embedding service
        $embeddingService = $this->createEmbeddingService($options['embedding'] ?? []);

        $memory = new \PhpSwarm\Memory\VectorMemory($embeddingService);

        // Store in registry
        $this->registry['memory_vector'] = $memory;

        return $memory;
    }

    /**
     * Create an embedding service.
     *
     * @param array<string, mixed> $options
     * @throws PhpSwarmException If the embedding service is not supported
     */
    public function createEmbeddingService(array $options = []): \PhpSwarm\Contract\Utility\EmbeddingServiceInterface
    {
        $provider = $options['provider'] ?? $this->config->get('embedding.provider', 'openai');

        return match ($provider) {
            'openai' => $this->createOpenAIEmbeddingService($options),
            default => throw new PhpSwarmException("Unsupported embedding provider: $provider"),
        };
    }

    /**
     * Create an OpenAI embedding service.
     *
     * @param array<string, mixed> $options
     */
    private function createOpenAIEmbeddingService(array $options = []): \PhpSwarm\Contract\Utility\EmbeddingServiceInterface
    {
        $config = [
            'api_key' => $options['api_key'] ?? $this->config->get('embedding.openai.api_key') ?? $this->config->get('llm.openai.api_key'),
            'model' => $options['model'] ?? $this->config->get('embedding.openai.model', 'text-embedding-3-small'),
            'base_url' => $options['base_url'] ?? $this->config->get('embedding.openai.base_url', 'https://api.openai.com/v1'),
            'timeout' => $options['timeout'] ?? $this->config->get('embedding.timeout', 30),
        ];

        $service = new \PhpSwarm\Utility\Embedding\OpenAIEmbeddingService($config);

        // Store in registry
        $this->registry['embedding_openai'] = $service;

        return $service;
    }

    /**
     * Create a tool instance by name.
     *
     * @param string $name The name of the tool
     * @param array<string, mixed> $options Additional options to override configuration
     * @return ToolInterface The tool instance
     * @throws PhpSwarmException If the tool is not supported
     */
    public function createTool(string $name, array $options = []): ToolInterface
    {
        $registryKey = "tool.$name";

        if (isset($this->registry[$registryKey]) && $options === []) {
            return $this->registry[$registryKey];
        }

        $tool = match ($name) {
            'calculator' => new CalculatorTool(),
            'web_search' => $this->createWebSearchTool($options),
            'weather' => $this->createWeatherTool($options),
            'file_system' => $this->createFileSystemTool($options),
            default => throw new PhpSwarmException("Unsupported tool: $name"),
        };

        if ($options === []) {
            $this->registry[$registryKey] = $tool;
        }

        return $tool;
    }

    /**
     * Create a web search tool.
     *
     * @param array<string, mixed> $options Additional options to override configuration
     * @return WebSearchTool The web search tool
     */
    private function createWebSearchTool(array $options = []): WebSearchTool
    {
        $config = [
            'api_key' => $options['api_key'] ?? $this->config->get('tool.web_search.api_key', ''),
            'search_engine_id' => $options['search_engine_id'] ?? $this->config->get('tool.web_search.engine_id', ''),
            'service' => $options['service'] ?? $this->config->get('tool.web_search.service', 'google'),
        ];

        return new WebSearchTool($config);
    }

    /**
     * Create a weather tool.
     *
     * @param array<string, mixed> $options Additional options to override configuration
     * @return WeatherTool The weather tool
     */
    private function createWeatherTool(array $options = []): WeatherTool
    {
        $config = [
            'api_key' => $options['api_key'] ?? $this->config->get('tool.weather.api_key', ''),
            'service' => $options['service'] ?? $this->config->get('tool.weather.service', 'openweathermap'),
        ];

        return new WeatherTool($config);
    }

    /**
     * Create a file system tool.
     *
     * @param array<string, mixed> $options Configuration options
     */
    private function createFileSystemTool(array $options = []): FileSystemTool
    {
        $defaultOptions = [
            'base_directory' => $this->config->get('tool.file_system.base_directory', getcwd()),
            'allowed_operations' => $this->config->get('tool.file_system.allowed_operations', null),
        ];

        $mergedOptions = array_merge($defaultOptions, $options);

        return new FileSystemTool($mergedOptions);
    }

    /**
     * Create an agent builder with preconfigured components.
     *
     * @param array<string, mixed> $options Additional options to override configuration
     * @return AgentBuilder The agent builder
     */
    public function createAgentBuilder(array $options = []): AgentBuilder
    {
        $builder = Agent::create();

        // Set LLM if configured
        if ($this->config->has('llm.provider') || isset($options['llm'])) {
            $llmOptions = $options['llm'] ?? [];
            $builder->withLLM($this->createLLM($llmOptions));
        }

        // Set memory if configured
        if ($this->config->has('memory.provider') || isset($options['memory'])) {
            $memoryOptions = $options['memory'] ?? [];
            $builder->withMemory($this->createMemory($memoryOptions));
        }

        // Set verbose logging if configured
        $verboseLogging = $options['verbose_logging'] ?? $this->config->get('agent.verbose', false);
        if ($verboseLogging) {
            $builder->withVerboseLogging();
        }

        // Set max iterations if configured
        $maxIterations = $options['max_iterations'] ?? $this->config->get('agent.max_iterations', 10);
        $builder->withMaxIterations((int) $maxIterations);

        // Set delegation if configured
        $allowDelegation = $options['allow_delegation'] ?? $this->config->get('agent.delegation', false);
        if ($allowDelegation) {
            $builder->allowDelegation();
        }

        // Add default tools if specified
        if (isset($options['tools']) && is_array($options['tools'])) {
            foreach ($options['tools'] as $toolName) {
                $builder->addTool($this->createTool($toolName));
            }
        }

        return $builder;
    }

    /**
     * Create an agent with the specified options.
     *
     * @param string $name The name of the agent
     * @param string $role The role of the agent
     * @param string $goal The goal of the agent
     * @param array<string, mixed> $options Additional options for the agent
     * @return Agent The configured agent
     */
    public function createAgent(string $name, string $role, string $goal, array $options = []): Agent
    {
        $builder = $this->createAgentBuilder($options);

        $builder->withName($name)
            ->withRole($role)
            ->withGoal($goal);

        if (isset($options['backstory'])) {
            $builder->withBackstory($options['backstory']);
        }

        return $builder->build();
    }

    /**
     * Get the configuration instance.
     */
    public function getConfig(): PhpSwarmConfig
    {
        return $this->config;
    }

    /**
     * Create a logger instance.
     *
     * @param array<string, mixed> $options Configuration options
     */
    public function createLogger(array $options = []): \PhpSwarm\Contract\Logger\LoggerInterface
    {
        $type = $options['type'] ?? $this->config->get('logger.type', 'file');

        return match ($type) {
            'file' => $this->createFileLogger($options),
            default => throw new PhpSwarmException("Unsupported logger type: $type"),
        };
    }

    /**
     * Create a file logger.
     *
     * @param array<string, mixed> $options
     */
    private function createFileLogger(array $options = []): \PhpSwarm\Contract\Logger\LoggerInterface
    {
        $logFile = $options['log_file'] ?? $this->config->get('logger.file.log_file', 'logs/phpswarm.log');
        $minLevel = $options['min_level'] ?? $this->config->get('logger.file.min_level', 'debug');
        $includeTimestamps = $options['include_timestamps'] ?? $this->config->get('logger.file.include_timestamps', true);
        $timestampFormat = $options['timestamp_format'] ?? $this->config->get('logger.file.timestamp_format', 'Y-m-d H:i:s');

        // Ensure log directory exists
        $logDir = dirname((string) $logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger = new \PhpSwarm\Logger\FileLogger(
            $logFile,
            $minLevel,
            $includeTimestamps,
            $timestampFormat
        );

        // Store in registry
        $this->registry['logger_file'] = $logger;

        return $logger;
    }

    /**
     * Create a performance monitor.
     *
     * @param array<string, mixed> $options Configuration options
     */
    public function createMonitor(array $options = []): \PhpSwarm\Contract\Logger\MonitorInterface
    {
        $withLogger = $options['with_logger'] ?? $this->config->get('monitor.with_logger', true);

        $logger = null;
        if ($withLogger) {
            $logger = $options['logger'] ?? null;

            if (!$logger && isset($this->registry['logger_file'])) {
                $logger = $this->registry['logger_file'];
            }

            if (!$logger) {
                $logger = $this->createLogger();
            }
        }

        $monitor = new \PhpSwarm\Logger\PerformanceMonitor($logger);

        // Store in registry
        $this->registry['monitor'] = $monitor;

        return $monitor;
    }

    /**
     * Create a workflow.
     *
     * @param string $name The name of the workflow
     * @param string $description The description of the workflow
     * @param array<string, mixed> $options Configuration options
     */
    public function createWorkflow(
        string $name,
        string $description = '',
        array $options = []
    ): \PhpSwarm\Contract\Workflow\WorkflowInterface {
        $logger = $options['logger'] ?? null;
        $monitor = $options['monitor'] ?? null;

        // Use existing logger if available
        if (!$logger && isset($this->registry['logger_file'])) {
            $logger = $this->registry['logger_file'];
        }

        // Use existing monitor if available
        if (!$monitor && isset($this->registry['monitor'])) {
            $monitor = $this->registry['monitor'];
        }

        // Create logger if needed
        if (!$logger && ($options['create_logger'] ?? $this->config->get('workflow.create_logger', true))) {
            $logger = $this->createLogger();
        }

        // Create monitor if needed
        if (!$monitor && ($options['create_monitor'] ?? $this->config->get('workflow.create_monitor', true))) {
            $monitor = $this->createMonitor(['logger' => $logger]);
        }

        $workflow = new \PhpSwarm\Workflow\Workflow($name, $description, $logger, $monitor);

        // Set max parallel steps if specified
        if (isset($options['max_parallel_steps'])) {
            $workflow->setMaxParallelSteps($options['max_parallel_steps']);
        } elseif ($this->config->has('workflow.max_parallel_steps')) {
            $workflow->setMaxParallelSteps($this->config->get('workflow.max_parallel_steps'));
        }

        return $workflow;
    }

    /**
     * Create an agent workflow step.
     *
     * @param string $name The name of the step
     * @param string $task The task to execute
     * @param string $description The description of the step
     * @param \PhpSwarm\Contract\Agent\AgentInterface|null $agent The agent to execute the step
     */
    public function createAgentStep(
        string $name,
        string $task,
        string $description = '',
        ?\PhpSwarm\Contract\Agent\AgentInterface $agent = null
    ): \PhpSwarm\Contract\Workflow\WorkflowStepInterface {
        return new \PhpSwarm\Workflow\AgentStep($name, $task, $description, $agent);
    }

    /**
     * Create a function workflow step.
     *
     * @param string $name The name of the step
     * @param callable $function The function to execute
     * @param string $description The description of the step
     */
    public function createFunctionStep(
        string $name,
        callable $function,
        string $description = ''
    ): \PhpSwarm\Contract\Workflow\WorkflowStepInterface {
        return new \PhpSwarm\Workflow\FunctionStep($name, $function, $description);
    }

    /**
     * Create a prompt manager or retrieve the existing one.
     *
     * @param array<string, mixed> $options Additional options to override configuration
     * @return PromptManagerInterface The prompt manager
     */
    public function createPromptManager(array $options = []): PromptManagerInterface
    {
        // Check if a prompt manager has already been created
        if (isset($this->registry['prompt_manager'])) {
            return $this->registry['prompt_manager'];
        }

        // Create a new prompt manager
        $promptManager = new PromptManager();

        // Store in registry
        $this->registry['prompt_manager'] = $promptManager;

        return $promptManager;
    }

    /**
     * Create a prompt template.
     *
     * @param string $name The name of the template
     * @param string $description The description of the template
     * @param string $content The template content
     * @param array<string, mixed> $options Additional options
     * @return PromptTemplateInterface The prompt template
     */
    public function createPromptTemplate(
        string $name,
        string $description,
        string $content,
        array $options = []
    ): PromptTemplateInterface {
        // Create the template
        $template = new PromptTemplate($name, $description, $content);

        // Register with manager if specified
        if (isset($options['register_with_manager']) && $options['register_with_manager'] === true) {
            $promptManager = $this->createPromptManager();
            $promptManager->registerTemplate($template);
        }

        return $template;
    }

    /**
     * Create a prompt.
     *
     * @param string $name The name of the prompt
     * @param string $description The description of the prompt
     * @param string $template The prompt template
     * @param array<string, array{description: string, required: bool}> $variables Variable definitions
     * @param array<string, mixed> $options Additional options
     * @return PromptInterface The prompt
     */
    public function createPrompt(
        string $name,
        string $description,
        string $template,
        array $variables = [],
        array $options = []
    ): PromptInterface {
        // Create the prompt
        $prompt = new BasePrompt($name, $description, $template);

        // Add variables
        foreach ($variables as $varName => $varInfo) {
            $prompt->addVariable(
                $varName,
                $varInfo['description'] ?? "Variable: $varName",
                $varInfo['required'] ?? true
            );
        }

        // Register with manager if specified
        if (isset($options['register_with_manager']) && $options['register_with_manager'] === true) {
            $promptManager = $this->createPromptManager();
            $promptManager->registerPrompt($prompt);
        }

        return $prompt;
    }

    /**
     * Create a prompt from a template.
     *
     * @param string $templateName The name of the template
     * @param string $promptName The name of the prompt
     * @param string $promptDescription The description of the prompt
     * @param array<string, mixed> $variables Variables to replace in the template
     * @param array<string, mixed> $options Additional options
     * @return PromptInterface|null The prompt or null if template not found
     */
    public function createPromptFromTemplate(
        string $templateName,
        string $promptName,
        string $promptDescription,
        array $variables = [],
        array $options = []
    ): ?PromptInterface {
        $promptManager = $this->createPromptManager();

        return $promptManager->createPromptFromTemplate(
            $templateName,
            $promptName,
            $promptDescription,
            $variables
        );
    }

    /**
     * Create a token counter for a specific provider.
     *
     * @param string $provider The provider name (openai, anthropic, generic)
     * @return TokenCounterInterface The token counter instance
     */
    public function createTokenCounter(string $provider = 'generic'): TokenCounterInterface
    {
        $counter = TokenCounterFactory::createForProvider($provider);

        // Store in registry
        $this->registry["token_counter_{$provider}"] = $counter;

        return $counter;
    }

    /**
     * Create a token counter for a specific model.
     *
     * @param string $model The model name
     * @return TokenCounterInterface The token counter instance
     */
    public function createTokenCounterForModel(string $model): TokenCounterInterface
    {
        $counter = TokenCounterFactory::createForModel($model);

        // Store in registry
        $this->registry["token_counter_model_{$model}"] = $counter;

        return $counter;
    }

    /**
     * Create the best available token counter.
     *
     * @return TokenCounterInterface The token counter instance
     */
    public function createBestTokenCounter(): TokenCounterInterface
    {
        if (!isset($this->registry['token_counter_best'])) {
            $this->registry['token_counter_best'] = TokenCounterFactory::createBest();
        }

        return $this->registry['token_counter_best'];
    }

    /**
     * Create a validator instance.
     *
     * @param array<string, mixed> $options Options for configuring the validator
     * @return ValidatorInterface The validator instance
     */
    public function createValidator(array $options = []): ValidatorInterface
    {
        $validator = new Validator();

        // Add any custom rules from options
        if (isset($options['custom_rules']) && is_array($options['custom_rules'])) {
            foreach ($options['custom_rules'] as $name => $rule) {
                if (is_callable($rule)) {
                    $validator->addRule($name, $rule);
                }
            }
        }

        // Store in registry
        $this->registry['validator'] = $validator;

        return $validator;
    }

    /**
     * Create a schema validator instance.
     *
     * @return SchemaValidator The schema validator instance
     */
    public function createSchemaValidator(): SchemaValidator
    {
        $validator = new SchemaValidator();

        // Store in registry
        $this->registry['schema_validator'] = $validator;

        return $validator;
    }

    /**
     * Create an event dispatcher for managing events and listeners.
     *
     * @param array<string, mixed> $options Configuration options
     */
    public function createEventDispatcher(array $options = []): EventDispatcherInterface
    {
        $dispatcher = new EventDispatcher();

        // Add logging subscriber if enabled
        $enableLogging = $options['enable_logging'] ?? $this->config->get('events.logging.enabled', true);
        $logAllEvents = $options['log_all_events'] ?? $this->config->get('events.logging.log_all_events', false);

        if ($enableLogging) {
            $logger = $this->createLogger();
            $loggingSubscriber = new LoggingEventSubscriber($logger, $logAllEvents);
            $dispatcher->addSubscriber($loggingSubscriber);
        }

        $this->registry['event_dispatcher'] = $dispatcher;

        return $dispatcher;
    }

    /**
     * Create a new event.
     *
     * @param string $name The event name
     * @param array<string, mixed> $data The event data
     * @param string $source The event source
     * @param bool $stoppable Whether the event can be stopped
     */
    public function createEvent(
        string $name,
        array $data = [],
        string $source = 'phpswarm',
        bool $stoppable = true
    ): EventInterface {
        return new Event($name, $data, $source, $stoppable);
    }

    /**
     * Create a logging event subscriber.
     *
     * @param bool $logAllEvents Whether to log all events or just important ones
     */
    public function createLoggingEventSubscriber(bool $logAllEvents = false): LoggingEventSubscriber
    {
        $logger = $this->createLogger();
        return new LoggingEventSubscriber($logger, $logAllEvents);
    }
}
