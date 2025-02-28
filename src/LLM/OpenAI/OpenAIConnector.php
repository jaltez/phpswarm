<?php

declare(strict_types=1);

namespace PhpSwarm\LLM\OpenAI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\LLM\LLMResponseInterface;
use PhpSwarm\Exception\LLM\LLMException;

/**
 * Connector for the OpenAI API.
 */
class OpenAIConnector implements LLMInterface
{
    /**
     * @var string OpenAI API key
     */
    private string $apiKey;

    /**
     * @var string Default model to use
     */
    private string $defaultModel;

    /**
     * @var string Base URL for the OpenAI API
     */
    private string $baseUrl;

    /**
     * @var Client HTTP client
     */
    private Client $client;

    /**
     * @var array<string, mixed> Default request options
     */
    private array $defaultOptions;

    /**
     * @var array<string, int> Token limits per model
     */
    private array $tokenLimits = [
        'gpt-4' => 8192,
        'gpt-4-32k' => 32768,
        'gpt-4-turbo' => 128000,
        'gpt-3.5-turbo' => 4096,
        'gpt-3.5-turbo-16k' => 16384,
    ];

    /**
     * Create a new OpenAIConnector instance.
     *
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? getenv('OPENAI_API_KEY');

        if (empty($this->apiKey)) {
            throw new LLMException('OpenAI API key is required');
        }

        $this->defaultModel = $config['model'] ?? getenv('OPENAI_MODEL') ?: 'gpt-4';
        $this->baseUrl = $config['base_url'] ?? 'https://api.openai.com/v1';

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->defaultOptions = [
            'temperature' => $config['temperature'] ?? 0.7,
            'max_tokens' => $config['max_tokens'] ?? null,
            'top_p' => $config['top_p'] ?? 1.0,
            'frequency_penalty' => $config['frequency_penalty'] ?? 0.0,
            'presence_penalty' => $config['presence_penalty'] ?? 0.0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function chat(array $messages, array $options = []): LLMResponseInterface
    {
        $options = $this->prepareOptions($options);

        $payload = [
            'model' => $options['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'],
            'top_p' => $options['top_p'],
            'frequency_penalty' => $options['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty'],
        ];

        if (!empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (!empty($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }

        if (!empty($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }

        try {
            $response = $this->client->post('/chat/completions', [
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            return new OpenAIResponse($responseData);
        } catch (GuzzleException $e) {
            throw new LLMException(
                "OpenAI API request failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function complete(string $prompt, array $options = []): LLMResponseInterface
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);
    }

    /**
     * {@inheritdoc}
     */
    public function stream(array $messages, callable $callback, array $options = []): void
    {
        $options = $this->prepareOptions($options);

        $payload = [
            'model' => $options['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'],
            'top_p' => $options['top_p'],
            'frequency_penalty' => $options['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty'],
            'stream' => true,
        ];

        if (!empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if (!empty($options['tool_choice'])) {
            $payload['tool_choice'] = $options['tool_choice'];
        }

        if (!empty($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }

        try {
            $response = $this->client->post('/chat/completions', [
                'json' => $payload,
                'stream' => true,
            ]);

            $stream = $response->getBody();

            while (!$stream->eof()) {
                $line = $stream->read(1024);

                if (empty($line)) {
                    continue;
                }

                $lines = explode("\n", $line);

                foreach ($lines as $dataLine) {
                    if (empty($dataLine)) {
                        continue;
                    }

                    // Remove "data: " prefix
                    if (str_starts_with($dataLine, 'data: ')) {
                        $dataLine = substr($dataLine, 6);
                    }

                    // Skip "[DONE]" marker
                    if (trim($dataLine) === '[DONE]') {
                        continue;
                    }

                    try {
                        $data = json_decode($dataLine, true);

                        if (isset($data['choices'][0]['delta']['content'])) {
                            $chunk = $data['choices'][0]['delta']['content'];
                            $callback($chunk, $data);
                        }
                    } catch (\JsonException $e) {
                        // Skip invalid JSON
                        continue;
                    }
                }
            }
        } catch (GuzzleException $e) {
            throw new LLMException(
                "OpenAI streaming API request failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenCount(string|array $input): int
    {
        // This is a very simplified token counting method
        // For production use, use a proper tokenizer like GPT-3-Encoder
        $text = is_string($input) ? $input : json_encode($input);
        $text = (string)$text;

        // Approximate token count: ~4 characters per token
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderName(): string
    {
        return 'OpenAI';
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFunctionCalling(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxContextLength(): int
    {
        return $this->tokenLimits[$this->defaultModel] ?? 4096;
    }

    /**
     * Prepare the options for the API request by merging with defaults.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function prepareOptions(array $options): array
    {
        return array_merge(
            $this->defaultOptions,
            ['model' => $this->defaultModel],
            $options
        );
    }
}
