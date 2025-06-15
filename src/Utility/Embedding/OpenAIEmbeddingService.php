<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Embedding;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PhpSwarm\Contract\Utility\EmbeddingServiceInterface;
use PhpSwarm\Exception\PhpSwarmException;

/**
 * OpenAI embedding service implementation.
 *
 * Uses OpenAI's embedding models to generate vector representations of text.
 */
class OpenAIEmbeddingService implements EmbeddingServiceInterface
{
    /**
     * @var Client HTTP client for API requests
     */
    private readonly Client $client;

    /**
     * @var string The OpenAI API key
     */
    private readonly string $apiKey;

    /**
     * @var string The embedding model to use
     */
    private readonly string $model;

    /**
     * @var string The base URL for the OpenAI API
     */
    private readonly string $baseUrl;

    /**
     * Model specifications for different embedding models.
     *
     * @var array<string, array<string, int>>
     */
    private const MODEL_SPECS = [
        'text-embedding-ada-002' => [
            'dimension' => 1536,
            'max_length' => 8192,
        ],
        'text-embedding-3-small' => [
            'dimension' => 1536,
            'max_length' => 8192,
        ],
        'text-embedding-3-large' => [
            'dimension' => 3072,
            'max_length' => 8192,
        ],
    ];

    /**
     * Create a new OpenAI embedding service.
     *
     * @param array<string, mixed> $config Configuration options
     * @throws PhpSwarmException If required configuration is missing
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? throw new PhpSwarmException('OpenAI API key is required');
        $this->model = $config['model'] ?? 'text-embedding-3-small';
        $this->baseUrl = $config['base_url'] ?? 'https://api.openai.com/v1';

        // Validate model
        if (!isset(self::MODEL_SPECS[$this->model])) {
            throw new PhpSwarmException("Unsupported OpenAI embedding model: {$this->model}");
        }

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'timeout' => $config['timeout'] ?? 30,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        // Validate text lengths
        foreach ($texts as $text) {
            if (strlen($text) > $this->getMaxTextLength()) {
                throw new PhpSwarmException(
                    "Text too long for embedding model {$this->model}. " .
                        "Maximum length: {$this->getMaxTextLength()}, provided: " . strlen($text)
                );
            }
        }

        try {
            $response = $this->client->post('/embeddings', [
                'json' => [
                    'model' => $this->model,
                    'input' => $texts,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['data']) || !is_array($data['data'])) {
                throw new PhpSwarmException('Invalid response format from OpenAI embedding API');
            }

            $embeddings = [];
            foreach ($data['data'] as $item) {
                if (!isset($item['embedding']) || !is_array($item['embedding'])) {
                    throw new PhpSwarmException('Invalid embedding data in API response');
                }
                $embeddings[] = $item['embedding'];
            }

            return $embeddings;
        } catch (GuzzleException $e) {
            throw new PhpSwarmException(
                "Failed to generate embeddings: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getDimension(): int
    {
        return self::MODEL_SPECS[$this->model]['dimension'];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getMaxTextLength(): int
    {
        return self::MODEL_SPECS[$this->model]['max_length'];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getModelName(): string
    {
        return $this->model;
    }
}
