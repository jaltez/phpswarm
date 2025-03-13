<?php

declare(strict_types=1);

namespace PhpSwarm\LLM\Ollama;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\LLM\LLMResponseInterface;
use PhpSwarm\Exception\LLM\LLMException;

/**
 * Connector for the Ollama API.
 */
class OllamaConnector implements LLMInterface
{
    /**
     * @var string Default model to use
     */
    private readonly string $defaultModel;

    /**
     * @var string Base URL for the Ollama API
     */
    private readonly string $baseUrl;

    /**
     * @var Client HTTP client
     */
    private readonly Client $client;

    /**
     * @var array<string, mixed> Default request options
     */
    private readonly array $defaultOptions;

    /**
     * @var array<string, int> Token limits per model
     */
    private array $tokenLimits = [
        'llama3' => 4096,
        'llama3.2' => 8192,
        'llama2' => 4096,
        'mistral' => 4096,
        'mistral-7b' => 4096,
        'gemma' => 8192,
        'vicuna' => 4096,
    ];

    /**
     * Create a new OllamaConnector instance.
     *
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->defaultModel = $config['model'] ?? getenv('OLLAMA_MODEL') ?: 'llama3';
        $this->baseUrl = $config['base_url'] ?? 'http://localhost:11434';

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $config['timeout'] ?? 120,
            'http_errors' => false,
        ]);

        $this->defaultOptions = [
            'temperature' => $config['temperature'] ?? 0.7,
            'top_p' => $config['top_p'] ?? 1.0,
            'max_tokens' => $config['max_tokens'] ?? null,
        ];
    }

    /**
     * Send a chat completion request to the Ollama API.
     *
     * @param array<array<string, string>> $messages The messages to send
     * @param array<string, mixed> $options Additional options for the request
     * @return LLMResponseInterface The response from the LLM
     * @throws LLMException if the API call fails
     */
    #[\Override]
    public function chat(array $messages, array $options = []): LLMResponseInterface
    {
        $options = $this->prepareOptions($options);
        $model = $options['model'] ?? $this->defaultModel;

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ];

        // Add additional parameters if provided
        foreach (['temperature', 'top_p', 'max_tokens'] as $param) {
            if (isset($options[$param])) {
                $payload[$param] = $options[$param];
            }
        }

        try {
            $response = $this->client->post('/api/chat', [
                'json' => $payload,
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new LLMException('Ollama API error: ' . ($data['error'] ?? $body));
            }

            return new OllamaResponse($data);
        } catch (GuzzleException $e) {
            throw new LLMException('Ollama API request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send a completion (non-chat) request to the Ollama API.
     *
     * @param string $prompt The prompt to send
     * @param array<string, mixed> $options Additional options for the request
     * @return LLMResponseInterface The response from the LLM
     * @throws LLMException if the API call fails
     */
    #[\Override]
    public function complete(string $prompt, array $options = []): LLMResponseInterface
    {
        $options = $this->prepareOptions($options);
        $model = $options['model'] ?? $this->defaultModel;

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        // Add additional parameters if provided
        foreach (['temperature', 'top_p', 'max_tokens'] as $param) {
            if (isset($options[$param])) {
                $payload[$param] = $options[$param];
            }
        }

        try {
            $response = $this->client->post('/api/generate', [
                'json' => $payload,
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new LLMException('Ollama API error: ' . ($data['error'] ?? $body));
            }

            return new OllamaResponse($data);
        } catch (GuzzleException $e) {
            throw new LLMException('Ollama API request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Stream a response from the Ollama API, processing chunks as they arrive.
     *
     * @param array<array<string, string>> $messages The messages to send
     * @param callable $callback The callback to handle each chunk
     * @param array<string, mixed> $options Additional options for the request
     * @throws LLMException if the API call fails
     */
    #[\Override]
    public function stream(array $messages, callable $callback, array $options = []): void
    {
        $options = $this->prepareOptions($options);
        $model = $options['model'] ?? $this->defaultModel;

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ];

        // Add additional parameters if provided
        foreach (['temperature', 'top_p', 'max_tokens'] as $param) {
            if (isset($options[$param])) {
                $payload[$param] = $options[$param];
            }
        }

        try {
            $response = $this->client->post('/api/chat', [
                'json' => $payload,
                'stream' => true,
                'on_headers' => function ($response): void {
                    if ($response->getStatusCode() !== 200) {
                        throw new LLMException('Ollama API error: ' . $response->getBody()->getContents());
                    }
                },
            ]);

            $body = $response->getBody();
            while (!$body->eof()) {
                $line = $body->read(1024);
                if ($line === '') {
                    continue;
                }
                if ($line === '0') {
                    continue;
                }

                $chunks = explode("\n", $line);
                foreach ($chunks as $chunk) {
                    if ($chunk === '') {
                        continue;
                    }
                    if ($chunk === '0') {
                        continue;
                    }
                    $data = json_decode($chunk, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue;
                    }

                    $callback($data);
                }
            }
        } catch (GuzzleException $e) {
            throw new LLMException('Ollama API streaming request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the number of tokens in the given input.
     * Note: This is an approximation as Ollama doesn't provide a tokenizing endpoint.
     *
     * @param string|array<mixed> $input The input to count tokens for
     * @return int The estimated number of tokens
     */
    #[\Override]
    public function getTokenCount(string|array $input): int
    {
        // Convert array to string if needed
        if (is_array($input)) {
            $text = '';
            foreach ($input as $item) {
                if (is_array($item) && isset($item['content'])) {
                    $text .= $item['content'] . ' ';
                } elseif (is_string($item)) {
                    $text .= $item . ' ';
                }
            }
            $input = $text;
        }

        // Simple approximation: 4 characters per token
        // This is very approximate but gives a rough estimate
        return (int) ceil(mb_strlen($input) / 4);
    }

    /**
     * Get the default model name used by this connector.
     */
    #[\Override]
    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    /**
     * Get the name of the provider.
     */
    #[\Override]
    public function getProviderName(): string
    {
        return 'Ollama';
    }

    /**
     * Get whether this connector supports function calling.
     */
    #[\Override]
    public function supportsFunctionCalling(): bool
    {
        return false; // Ollama doesn't support function calling in the same way as OpenAI
    }

    /**
     * Get whether this connector supports streaming.
     */
    #[\Override]
    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * Get the maximum context length supported by the default model.
     */
    #[\Override]
    public function getMaxContextLength(): int
    {
        return $this->tokenLimits[$this->defaultModel] ?? 4096;
    }

    /**
     * Prepare the options for an API request.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function prepareOptions(array $options): array
    {
        return array_merge($this->defaultOptions, $options);
    }
} 