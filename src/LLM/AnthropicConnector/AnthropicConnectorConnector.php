<?php

declare(strict_types=1);

namespace PhpSwarm\LLM\AnthropicConnector;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\LLM\LLMResponseInterface;
use PhpSwarm\Exception\LLM\LLMException;

/**
 * AnthropicConnectorConnector - Integration with AnthropicConnector API
 */
class AnthropicConnectorConnector implements LLMInterface
{
    /**
     * @var Client The HTTP client
     */
    private Client $client;

    /**
     * @var string The API key
     */
    private string $apiKey;

    /**
     * @var string The default model to use
     */
    private string $defaultModel;

    /**
     * @var float The default temperature
     */
    private float $defaultTemperature;

    /**
     * @var int|null The default maximum tokens
     */
    private ?int $defaultMaxTokens;

    /**
     * @var string The API base URL
     */
    private string $apiBaseUrl;

    /**
     * Create a new AnthropicConnectorConnector instance
     *
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? getenv('AnthropicConnector_API_KEY') ?: '';
        $this->defaultModel = $config['model'] ?? getenv('AnthropicConnector_MODEL') ?: 'claude-3-opus-20240229';
        $this->defaultTemperature = (float) ($config['temperature'] ?? getenv('AnthropicConnector_TEMPERATURE') ?: 0.7);
        
        // Handle max tokens
        $configMaxTokens = isset($config['max_tokens']) ? (int) $config['max_tokens'] : null;
        $envMaxTokens = getenv('AnthropicConnector_MAX_TOKENS') ? (int) getenv('AnthropicConnector_MAX_TOKENS') : null;
        $this->defaultMaxTokens = $configMaxTokens ?? $envMaxTokens;
        
        $this->apiBaseUrl = $config['api_base_url'] ?? getenv('AnthropicConnector_API_BASE_URL') ?: 'https://api.example.com/v1';

        $clientConfig = [
            'base_uri' => $this->apiBaseUrl,
            'timeout' => $config['timeout'] ?? 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ];

        $this->client = new Client($clientConfig);

        if (empty($this->apiKey)) {
            throw new LLMException('AnthropicConnector API key is required');
        }
    }

    /**
     * Send a chat completion request to the LLM
     *
     * @param array<array<string, string>> $messages The messages to send
     * @param array<string, mixed> $options Additional options for the request
     * @return LLMResponseInterface The response from the LLM
     * @throws LLMException If the request fails
     */
    public function chat(array $messages, array $options = []): LLMResponseInterface
    {
        try {
            $requestOptions = $this->prepareOptions($options);

            $payload = [
                'model' => $requestOptions['model'],
                'messages' => $messages,
                'temperature' => $requestOptions['temperature'],
            ];

            if (isset($requestOptions['max_tokens'])) {
                $payload['max_tokens'] = $requestOptions['max_tokens'];
            }

            if (isset($requestOptions['tools']) && $this->supportsFunctionCalling()) {
                $payload['tools'] = $requestOptions['tools'];
                $payload['tool_choice'] = $requestOptions['tool_choice'] ?? 'auto';
            }

            $response = $this->client->post('chat/completions', [
                'json' => $payload,
            ]);

            $responseData = json_decode((string) $response->getBody(), true);

            return new AnthropicConnectorResponse($responseData);
        } catch (GuzzleException $e) {
            throw new LLMException('Failed to send chat request to AnthropicConnector: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send a completion (non-chat) request to the LLM
     *
     * @param string $prompt The prompt to send
     * @param array<string, mixed> $options Additional options for the request
     * @return LLMResponseInterface The response from the LLM
     * @throws LLMException If the request fails
     */
    public function complete(string $prompt, array $options = []): LLMResponseInterface
    {
        // Convert the prompt to a chat message and use the chat endpoint
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);
    }

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
        try {
            $requestOptions = $this->prepareOptions($options);

            $payload = [
                'model' => $requestOptions['model'],
                'messages' => $messages,
                'temperature' => $requestOptions['temperature'],
                'stream' => true,
            ];

            if (isset($requestOptions['max_tokens'])) {
                $payload['max_tokens'] = $requestOptions['max_tokens'];
            }

            if (isset($requestOptions['tools']) && $this->supportsFunctionCalling()) {
                $payload['tools'] = $requestOptions['tools'];
                $payload['tool_choice'] = $requestOptions['tool_choice'] ?? 'auto';
            }

            $response = $this->client->post('chat/completions', [
                'json' => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();

            while (!$body->eof()) {
                $line = $this->readLine($body);

                if (empty($line)) {
                    continue;
                }

                if ($line === 'data: [DONE]') {
                    break;
                }

                if (strpos($line, 'data: ') === 0) {
                    $data = json_decode(substr($line, 6), true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($data['choices'][0])) {
                        $chunk = new AnthropicConnectorResponse($data);
                        $callback($chunk);
                    }
                }
            }
        } catch (GuzzleException $e) {
            throw new LLMException('Failed to stream response from LLM: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Read a line from the stream
     *
     * @param \Psr\Http\Message\StreamInterface $stream The stream to read from
     * @return string The line read from the stream
     */
    private function readLine($stream): string
    {
        $buffer = '';
        while (!$stream->eof()) {
            $byte = $stream->read(1);
            if ($byte === "\n") {
                break;
            }
            $buffer .= $byte;
        }
        return trim($buffer);
    }

    /**
     * Get the number of tokens in the given input
     *
     * @param string|array<mixed> $input The input to count tokens for
     * @return int The number of tokens
     */
    public function getTokenCount(string|array $input): int
    {
        // Implement a token counting algorithm or call an API
        // This is a simple approximation
        if (is_string($input)) {
            // Roughly 4 characters per token for English text
            return (int) ceil(mb_strlen($input) / 4);
        }

        if (is_array($input)) {
            $count = 0;
            foreach ($input as $message) {
                if (isset($message['content']) && is_string($message['content'])) {
                    $count += (int) ceil(mb_strlen($message['content']) / 4);
                }
            }
            return $count;
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
        return $this->defaultModel;
    }

    /**
     * Get the name of the provider
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return 'AnthropicConnector';
    }

    /**
     * Get whether this connector supports function calling
     *
     * @return bool
     */
    public function supportsFunctionCalling(): bool
    {
        return true;
    }

    /**
     * Get whether this connector supports streaming
     *
     * @return bool
     */
    public function supportsStreaming(): bool
    {
        return true;
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
     * @param array<string, mixed> $options The options to prepare
     * @return array<string, mixed> The prepared options
     */
    private function prepareOptions(array $options): array
    {
        return [
            'model' => $options['model'] ?? $this->defaultModel,
            'temperature' => $options['temperature'] ?? $this->defaultTemperature,
            'max_tokens' => $options['max_tokens'] ?? $this->defaultMaxTokens,
            'tools' => $options['tools'] ?? [],
            'tool_choice' => $options['tool_choice'] ?? null,
        ];
    }
}
