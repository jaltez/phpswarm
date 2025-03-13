<?php

declare(strict_types=1);

namespace PhpSwarm\LLM\Anthropic;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\LLM\LLMResponseInterface;
use PhpSwarm\Exception\LLM\LLMException;

/**
 * Connector for the Anthropic Claude API.
 */
class AnthropicConnector implements LLMInterface
{
    /**
     * @var string API key for authentication
     */
    private readonly string $apiKey;

    /**
     * @var string Default model to use
     */
    private readonly string $defaultModel;

    /**
     * @var string Base URL for API requests
     */
    private readonly string $baseUrl;

    /**
     * @var Client HTTP client for making requests
     */
    private readonly Client $client;

    /**
     * @var array<string, mixed> Default options for API requests
     */
    private readonly array $defaultOptions;

    /**
     * @var array<string, int> Model token limits
     */
    private const array MODEL_TOKEN_LIMITS = [
        'claude-3-opus-20240229' => 200000,
        'claude-3-sonnet-20240229' => 200000,
        'claude-3-haiku-20240307' => 200000,
        'claude-2.1' => 100000,
        'claude-2.0' => 100000,
        'claude-instant-1.2' => 100000,
    ];

    /**
     * Create a new AnthropicConnector instance.
     *
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? getenv('ANTHROPIC_API_KEY');

        if (empty($this->apiKey)) {
            throw new LLMException('Anthropic API key is required');
        }

        $this->defaultModel = $config['model'] ?? getenv('ANTHROPIC_MODEL') ?: 'claude-3-sonnet-20240229';
        $this->baseUrl = $config['base_url'] ?? 'https://api.anthropic.com';

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->defaultOptions = [
            'temperature' => $config['temperature'] ?? 0.7,
            'max_tokens' => $config['max_tokens'] ?? 4096,
            'top_p' => $config['top_p'] ?? 1.0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function chat(array $messages, array $options = []): LLMResponseInterface
    {
        $options = $this->prepareOptions($options);

        // Convert messages to Anthropic format (system, user, assistant)
        $formattedMessages = $this->formatMessages($messages);

        $payload = [
            'model' => $options['model'],
            'messages' => $formattedMessages,
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'top_p' => $options['top_p'],
        ];

        // Add tool specification if provided
        if (isset($options['tools']) && !empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        try {
            $response = $this->client->post('/v1/messages', [
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            return new AnthropicResponse($responseData);
        } catch (GuzzleException $e) {
            throw new LLMException(
                'Failed to send message to Anthropic API: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function complete(string $prompt, array $options = []): LLMResponseInterface
    {
        // Convert the prompt to a message and use chat
        $messages = [
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ];

        return $this->chat($messages, $options);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function stream(array $messages, callable $callback, array $options = []): void
    {
        $options = $this->prepareOptions($options);

        // Convert messages to Anthropic format
        $formattedMessages = $this->formatMessages($messages);

        $payload = [
            'model' => $options['model'],
            'messages' => $formattedMessages,
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'top_p' => $options['top_p'],
            'stream' => true,
        ];

        // Add tool specification if provided
        if (isset($options['tools']) && !empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        try {
            $response = $this->client->post('/v1/messages', [
                'json' => $payload,
                'stream' => true,
                'decode_content' => true,
            ]);

            $body = $response->getBody();

            // Process each line as it comes in
            while (!$body->eof()) {
                $line = $body->readline();
                // Skip empty lines or lines not starting with "data:"
                if (empty($line)) {
                    continue;
                }
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }

                // Remove "data: " prefix and parse JSON
                $data = substr($line, 6);

                // Handle [DONE] marker
                if (trim($data) === '[DONE]') {
                    break;
                }

                $chunk = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $callback($chunk);
                }
            }
        } catch (GuzzleException $e) {
            throw new LLMException(
                'Failed to stream from Anthropic API: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTokenCount(string|array $input): int
    {
        // This is a simple estimation as Anthropic doesn't provide an official tokenizer
        // For more accurate counts, an external tokenizer library should be used
        if (is_array($input)) {
            $input = json_encode($input);
        }

        // Very simple estimation: 1 token ≈ 4 characters
        return (int) ceil(mb_strlen($input) / 4);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getProviderName(): string
    {
        return 'Anthropic';
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function supportsFunctionCalling(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getMaxContextLength(): int
    {
        return self::MODEL_TOKEN_LIMITS[$this->defaultModel] ?? 100000;
    }

    /**
     * Format messages from standard format to Anthropic format.
     *
     * @param array<array<string, string>> $messages
     * @return array<array<string, string|array<string, string>>>
     */
    private function formatMessages(array $messages): array
    {
        $formattedMessages = [];

        foreach ($messages as $message) {
            $role = $message['role'];
            $content = $message['content'];

            // Handle system message - it's handled differently in Anthropic
            if ($role === 'system') {
                // Add system message as a special field in the request
                // We'll handle this outside this function
                continue;
            }

            // Map roles to Anthropic roles
            $anthropicRole = match ($role) {
                'user' => 'user',
                'assistant' => 'assistant',
                default => 'user', // Default to user for unknown roles
            };

            $formattedMessage = [
                'role' => $anthropicRole,
            ];

            // Handle content
            if (is_array($content)) {
                // Multi-modal content (text and images)
                $formattedContent = [];

                foreach ($content as $item) {
                    if (isset($item['type']) && $item['type'] === 'image') {
                        $formattedContent[] = [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $item['image_type'] ?? 'image/jpeg',
                                'data' => $item['image_url'] ?? $item['data'],
                            ],
                        ];
                    } else {
                        $formattedContent[] = [
                            'type' => 'text',
                            'text' => $item['text'],
                        ];
                    }
                }

                $formattedMessage['content'] = $formattedContent;
            } else {
                // Plain text content
                $formattedMessage['content'] = $content;
            }

            $formattedMessages[] = $formattedMessage;
        }

        return $formattedMessages;
    }

    /**
     * Prepare options by merging with defaults.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function prepareOptions(array $options): array
    {
        return array_merge($this->defaultOptions, [
            'model' => $options['model'] ?? $this->defaultModel,
        ], $options);
    }
}
