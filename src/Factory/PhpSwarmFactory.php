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
}
