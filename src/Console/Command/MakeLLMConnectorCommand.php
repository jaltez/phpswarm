<?php

declare(strict_types=1);

namespace PhpSwarm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to scaffold a new LLM connector class
 */
class MakeLLMConnectorCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('make:llm-connector')
            ->setDescription('Create a new LLM connector class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the LLM provider (e.g., "Gemini", "Mistral")')
            ->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory to create the connector in', 'src/LLM')
            ->addOption('streaming', 's', InputOption::VALUE_NONE, 'Whether the connector supports streaming')
            ->addOption('function-calling', 'f', InputOption::VALUE_NONE, 'Whether the connector supports function calling')
            ->addOption('default-model', 'm', InputOption::VALUE_OPTIONAL, 'The default model to use', 'gpt-4');
    }

    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $directory = $input->getOption('directory');
        $supportsStreaming = $input->getOption('streaming');
        $supportsFunctionCalling = $input->getOption('function-calling');
        $defaultModel = $input->getOption('default-model');

        // Ensure the name has the correct format
        $providerName = $this->formatProviderName($name);
        $connectorClassName = $providerName . 'Connector';
        $responseClassName = $providerName . 'Response';

        // Create the provider directory if it doesn't exist
        $providerDirectory = $directory . '/' . $providerName;
        if (!is_dir($providerDirectory)) {
            mkdir($providerDirectory, 0755, true);
        }

        // Generate the namespace based on the directory
        $namespace = $this->generateNamespace($providerDirectory);

        // Generate file paths
        $connectorFilePath = $providerDirectory . '/' . $connectorClassName . '.php';
        $responseFilePath = $providerDirectory . '/' . $responseClassName . '.php';

        // Check if the files already exist
        if (file_exists($connectorFilePath)) {
            $io->error(sprintf('LLM connector "%s" already exists at "%s"', $connectorClassName, $connectorFilePath));
            return Command::FAILURE;
        }

        if (file_exists($responseFilePath)) {
            $io->error(sprintf('LLM response "%s" already exists at "%s"', $responseClassName, $responseFilePath));
            return Command::FAILURE;
        }

        // Generate the connector class content
        $connectorContent = $this->generateConnectorClass(
            $namespace,
            $connectorClassName,
            $responseClassName,
            $providerName,
            $defaultModel,
            $supportsStreaming,
            $supportsFunctionCalling
        );

        // Generate the response class content
        $responseContent = $this->generateResponseClass(
            $namespace,
            $responseClassName,
            $providerName,
            $supportsFunctionCalling
        );

        // Write the content to the files
        file_put_contents($connectorFilePath, $connectorContent);
        file_put_contents($responseFilePath, $responseContent);

        $io->success([
            sprintf('LLM connector "%s" created successfully at "%s"', $connectorClassName, $connectorFilePath),
            sprintf('LLM response "%s" created successfully at "%s"', $responseClassName, $responseFilePath)
        ]);

        return Command::SUCCESS;
    }

    /**
     * Format the provider name to ensure it follows PHP conventions
     */
    private function formatProviderName(string $name): string
    {
        // Convert to PascalCase
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    /**
     * Generate the namespace based on the directory
     */
    private function generateNamespace(string $directory): string
    {
        // Convert directory path to namespace
        $namespace = str_replace('/', '\\', $directory);

        // Remove src/ or src\ prefix
        $namespace = preg_replace('/^src[\/\\\\]/', '', $namespace);

        // Add PhpSwarm prefix
        return 'PhpSwarm\\' . $namespace;
    }

    /**
     * Generate the connector class content
     */
    private function generateConnectorClass(
        string $namespace,
        string $className,
        string $responseClassName,
        string $providerName,
        string $defaultModel,
        bool $supportsStreaming,
        bool $supportsFunctionCalling
    ): string {
        $streamingCode = $supportsStreaming ? $this->generateStreamingMethod($responseClassName) : $this->generateNonStreamingMethod();
        $functionCallingCode = $supportsFunctionCalling ? 'true' : 'false';
        $streamingSupportCode = $supportsStreaming ? 'true' : 'false';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\LLM\LLMResponseInterface;
use PhpSwarm\Exception\LLM\LLMException;

/**
 * {$className} - Integration with {$providerName} API
 */
class {$className} implements LLMInterface
{
    /**
     * @var Client The HTTP client
     */
    private Client \$client;
    
    /**
     * @var string The API key
     */
    private string \$apiKey;
    
    /**
     * @var string The default model to use
     */
    private string \$defaultModel;
    
    /**
     * @var float The default temperature
     */
    private float \$defaultTemperature;
    
    /**
     * @var int|null The default maximum tokens
     */
    private ?int \$defaultMaxTokens;
    
    /**
     * @var string The API base URL
     */
    private string \$apiBaseUrl;
    
    /**
     * Create a new {$className} instance
     *
     * @param array<string, mixed> \$config Configuration options
     */
    public function __construct(array \$config = [])
    {
        \$this->apiKey = \$config['api_key'] ?? getenv('{$providerName}_API_KEY') ?: '';
        \$this->defaultModel = \$config['model'] ?? getenv('{$providerName}_MODEL') ?: '{$defaultModel}';
        \$this->defaultTemperature = (float) (\$config['temperature'] ?? getenv('{$providerName}_TEMPERATURE') ?: 0.7);
        \$this->defaultMaxTokens = isset(\$config['max_tokens']) ? (int) \$config['max_tokens'] : (getenv('{$providerName}_MAX_TOKENS') ? (int) getenv('{$providerName}_MAX_TOKENS') : null);
        \$this->apiBaseUrl = \$config['api_base_url'] ?? getenv('{$providerName}_API_BASE_URL') ?: 'https://api.example.com/v1';
        
        \$clientConfig = [
            'base_uri' => \$this->apiBaseUrl,
            'timeout' => \$config['timeout'] ?? 30,
            'headers' => [
                'Authorization' => 'Bearer ' . \$this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ];
        
        \$this->client = new Client(\$clientConfig);
        
        if (empty(\$this->apiKey)) {
            throw new LLMException('{$providerName} API key is required');
        }
    }
    
    /**
     * Send a chat completion request to the LLM
     *
     * @param array<array<string, string>> \$messages The messages to send
     * @param array<string, mixed> \$options Additional options for the request
     * @return LLMResponseInterface The response from the LLM
     * @throws LLMException If the request fails
     */
    public function chat(array \$messages, array \$options = []): LLMResponseInterface
    {
        try {
            \$requestOptions = \$this->prepareOptions(\$options);
            
            \$payload = [
                'model' => \$requestOptions['model'],
                'messages' => \$messages,
                'temperature' => \$requestOptions['temperature'],
            ];
            
            if (isset(\$requestOptions['max_tokens'])) {
                \$payload['max_tokens'] = \$requestOptions['max_tokens'];
            }
            
            if (isset(\$requestOptions['tools']) && \$this->supportsFunctionCalling()) {
                \$payload['tools'] = \$requestOptions['tools'];
                \$payload['tool_choice'] = \$requestOptions['tool_choice'] ?? 'auto';
            }
            
            \$response = \$this->client->post('chat/completions', [
                'json' => \$payload,
            ]);
            
            \$responseData = json_decode((string) \$response->getBody(), true);
            
            return new {$responseClassName}(\$responseData);
        } catch (GuzzleException \$e) {
            throw new LLMException('Failed to send chat request to {$providerName}: ' . \$e->getMessage(), 0, \$e);
        }
    }
    
    /**
     * Send a completion (non-chat) request to the LLM
     *
     * @param string \$prompt The prompt to send
     * @param array<string, mixed> \$options Additional options for the request
     * @return LLMResponseInterface The response from the LLM
     * @throws LLMException If the request fails
     */
    public function complete(string \$prompt, array \$options = []): LLMResponseInterface
    {
        // Convert the prompt to a chat message and use the chat endpoint
        return \$this->chat([
            ['role' => 'user', 'content' => \$prompt],
        ], \$options);
    }
    
{$streamingCode}
    
    /**
     * Get the number of tokens in the given input
     *
     * @param string|array<mixed> \$input The input to count tokens for
     * @return int The number of tokens
     */
    public function getTokenCount(string|array \$input): int
    {
        // Implement a token counting algorithm or call an API
        // This is a simple approximation
        if (is_string(\$input)) {
            // Roughly 4 characters per token for English text
            return (int) ceil(mb_strlen(\$input) / 4);
        }
        
        if (is_array(\$input)) {
            \$count = 0;
            foreach (\$input as \$message) {
                if (isset(\$message['content']) && is_string(\$message['content'])) {
                    \$count += (int) ceil(mb_strlen(\$message['content']) / 4);
                }
            }
            return \$count;
        }
        
        return 0;
    }
    
    /**
     * Get the default model name used by this connector
     *
     * @return string
     */
    public function getDefaultModel(): string
    {
        return \$this->defaultModel;
    }
    
    /**
     * Get the name of the provider
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return '{$providerName}';
    }
    
    /**
     * Get whether this connector supports function calling
     *
     * @return bool
     */
    public function supportsFunctionCalling(): bool
    {
        return {$functionCallingCode};
    }
    
    /**
     * Get whether this connector supports streaming
     *
     * @return bool
     */
    public function supportsStreaming(): bool
    {
        return {$streamingSupportCode};
    }
    
    /**
     * Get the maximum context length supported by the default model
     *
     * @return int
     */
    public function getMaxContextLength(): int
    {
        // Update this based on the actual model limits
        return 8192;
    }
    
    /**
     * Prepare the options for the request
     *
     * @param array<string, mixed> \$options The options to prepare
     * @return array<string, mixed> The prepared options
     */
    private function prepareOptions(array \$options): array
    {
        return [
            'model' => \$options['model'] ?? \$this->defaultModel,
            'temperature' => \$options['temperature'] ?? \$this->defaultTemperature,
            'max_tokens' => \$options['max_tokens'] ?? \$this->defaultMaxTokens,
            'tools' => \$options['tools'] ?? [],
            'tool_choice' => \$options['tool_choice'] ?? null,
        ];
    }
}
PHP;
    }

    /**
     * Generate the streaming method implementation
     */
    private function generateStreamingMethod(string $responseClassName): string
    {
        return <<<PHP
    /**
     * Stream a response from the LLM, processing chunks as they arrive
     *
     * @param array<array<string, string>> \$messages The messages to send
     * @param callable \$callback The callback to handle each chunk
     * @param array<string, mixed> \$options Additional options for the request
     * @return void
     * @throws LLMException If the request fails
     */
    public function stream(array \$messages, callable \$callback, array \$options = []): void
    {
        try {
            \$requestOptions = \$this->prepareOptions(\$options);
            
            \$payload = [
                'model' => \$requestOptions['model'],
                'messages' => \$messages,
                'temperature' => \$requestOptions['temperature'],
                'stream' => true,
            ];
            
            if (isset(\$requestOptions['max_tokens'])) {
                \$payload['max_tokens'] = \$requestOptions['max_tokens'];
            }
            
            if (isset(\$requestOptions['tools']) && \$this->supportsFunctionCalling()) {
                \$payload['tools'] = \$requestOptions['tools'];
                \$payload['tool_choice'] = \$requestOptions['tool_choice'] ?? 'auto';
            }
            
            \$response = \$this->client->post('chat/completions', [
                'json' => \$payload,
                'stream' => true,
            ]);
            
            \$body = \$response->getBody();
            
            while (!$body->eof()) {
                \$line = \$this->readLine(\$body);
                
                if (empty(\$line)) {
                    continue;
                }
                
                if (\$line === 'data: [DONE]') {
                    break;
                }
                
                if (strpos(\$line, 'data: ') === 0) {
                    \$data = json_decode(substr(\$line, 6), true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset(\$data['choices'][0])) {
                        \$chunk = new {$responseClassName}(\$data);
                        \$callback(\$chunk);
                    }
                }
            }
        } catch (GuzzleException \$e) {
            throw new LLMException('Failed to stream response from LLM: ' . \$e->getMessage(), 0, \$e);
        }
    }
    
    /**
     * Read a line from the stream
     *
     * @param \Psr\Http\Message\StreamInterface \$stream The stream to read from
     * @return string The line read from the stream
     */
    private function readLine(\$stream): string
    {
        \$buffer = '';
        while (!$stream->eof()) {
            \$byte = \$stream->read(1);
            if (\$byte === "\\n") {
                break;
            }
            \$buffer .= \$byte;
        }
        return trim(\$buffer);
    }
PHP;
    }

    /**
     * Generate a non-streaming method implementation
     */
    private function generateNonStreamingMethod(): string
    {
        return <<<'PHP'
    /**
     * Stream a response from the LLM, processing chunks as they arrive
     *
     * @param array<array<string, string>> $messages The messages to send
     * @param callable $callback The callback to handle each chunk
     * @param array<string, mixed> $options Additional options for the request
     * @return void
     * @throws LLMException If the request fails
     */
    public function stream(array $messages, callable $callback, array $options = []): void
    {
        throw new LLMException('Streaming is not supported by this LLM connector');
    }
PHP;
    }

    /**
     * Generate the response class content
     */
    private function generateResponseClass(
        string $namespace,
        string $className,
        string $providerName,
        bool $supportsFunctionCalling
    ): string {
        $toolCallsCode = $supportsFunctionCalling ? $this->generateToolCallsCode() : $this->generateNoToolCallsCode();

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use PhpSwarm\Contract\LLM\LLMResponseInterface;

/**
 * {$className} - Response from {$providerName} API
 */
class {$className} implements LLMResponseInterface
{
    /**
     * @var array<string, mixed> The raw response data
     */
    private array \$rawResponse;
    
    /**
     * @var string The content of the response
     */
    private string \$content = '';
    
    /**
     * @var array<array<string, mixed>> The tool calls in the response
     */
    private array \$toolCalls = [];
    
    /**
     * @var bool Whether the response has tool calls
     */
    private bool \$hasToolCalls = false;
    
    /**
     * @var int|null The number of prompt tokens used
     */
    private ?int \$promptTokens = null;
    
    /**
     * @var int|null The number of completion tokens used
     */
    private ?int \$completionTokens = null;
    
    /**
     * @var int|null The total number of tokens used
     */
    private ?int \$totalTokens = null;
    
    /**
     * @var string The model used for this response
     */
    private string \$model = '';
    
    /**
     * @var array<string, mixed> Additional metadata about the response
     */
    private array \$metadata = [];
    
    /**
     * @var string|null The finish reason provided by the LLM
     */
    private ?string \$finishReason = null;
    
    /**
     * Create a new {$className} instance
     *
     * @param array<string, mixed> \$rawResponse The raw response data
     */
    public function __construct(array \$rawResponse)
    {
        \$this->rawResponse = \$rawResponse;
        \$this->parseResponse();
    }
    
    /**
     * Parse the raw response data
     */
    private function parseResponse(): void
    {
        // Extract the model
        \$this->model = \$this->rawResponse['model'] ?? '';
        
        // Extract the content
        if (isset(\$this->rawResponse['choices'][0]['message']['content'])) {
            \$this->content = \$this->rawResponse['choices'][0]['message']['content'] ?? '';
        } elseif (isset(\$this->rawResponse['choices'][0]['delta']['content'])) {
            \$this->content = \$this->rawResponse['choices'][0]['delta']['content'] ?? '';
        }
        
        // Extract token usage
        if (isset(\$this->rawResponse['usage'])) {
            \$this->promptTokens = \$this->rawResponse['usage']['prompt_tokens'] ?? null;
            \$this->completionTokens = \$this->rawResponse['usage']['completion_tokens'] ?? null;
            \$this->totalTokens = \$this->rawResponse['usage']['total_tokens'] ?? null;
        }
        
        // Extract finish reason
        \$this->finishReason = \$this->rawResponse['choices'][0]['finish_reason'] ?? null;
        
{$toolCallsCode}
    }
    
    /**
     * Get the main text/content of the response
     *
     * @return string
     */
    public function getContent(): string
    {
        return \$this->content;
    }
    
    /**
     * Get the raw response data from the LLM provider
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return \$this->rawResponse;
    }
    
    /**
     * Get the tool calls from the response, if any
     *
     * @return array<array<string, mixed>>
     */
    public function getToolCalls(): array
    {
        return \$this->toolCalls;
    }
    
    /**
     * Get whether the response contains tool calls
     *
     * @return bool
     */
    public function hasToolCalls(): bool
    {
        return \$this->hasToolCalls;
    }
    
    /**
     * Get the number of prompt tokens used
     *
     * @return int|null
     */
    public function getPromptTokens(): ?int
    {
        return \$this->promptTokens;
    }
    
    /**
     * Get the number of completion tokens used
     *
     * @return int|null
     */
    public function getCompletionTokens(): ?int
    {
        return \$this->completionTokens;
    }
    
    /**
     * Get the total number of tokens used
     *
     * @return int|null
     */
    public function getTotalTokens(): ?int
    {
        return \$this->totalTokens;
    }
    
    /**
     * Get the model used for this response
     *
     * @return string
     */
    public function getModel(): string
    {
        return \$this->model;
    }
    
    /**
     * Get any additional metadata about the response
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return \$this->metadata;
    }
    
    /**
     * Get the finish reason provided by the LLM
     *
     * @return string|null
     */
    public function getFinishReason(): ?string
    {
        return \$this->finishReason;
    }
    
    /**
     * Add metadata to the response
     *
     * @param string \$key The metadata key
     * @param mixed \$value The metadata value
     * @return self
     */
    public function addMetadata(string \$key, mixed \$value): self
    {
        \$this->metadata[\$key] = \$value;
        return \$this;
    }
}
PHP;
    }

    /**
     * Generate code for parsing tool calls
     */
    private function generateToolCallsCode(): string
    {
        return <<<'PHP'
        // Extract tool calls
        if (isset($this->rawResponse['choices'][0]['message']['tool_calls'])) {
            $this->toolCalls = $this->rawResponse['choices'][0]['message']['tool_calls'];
            $this->hasToolCalls = !empty($this->toolCalls);
        } elseif (isset($this->rawResponse['choices'][0]['delta']['tool_calls'])) {
            $this->toolCalls = $this->rawResponse['choices'][0]['delta']['tool_calls'];
            $this->hasToolCalls = !empty($this->toolCalls);
        }
PHP;
    }

    /**
     * Generate code for no tool calls
     */
    private function generateNoToolCallsCode(): string
    {
        return <<<'PHP'
        // This LLM provider does not support tool calls
        $this->toolCalls = [];
        $this->hasToolCalls = false;
PHP;
    }
}
