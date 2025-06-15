<?php

declare(strict_types=1);

namespace PhpSwarm\Memory;

use GuzzleHttp\Client;
use PhpSwarm\Contract\Memory\VectorMemoryInterface;
use PhpSwarm\Contract\Utility\EmbeddingServiceInterface;
use PhpSwarm\Exception\Memory\MemoryException;

class ChromaMemory implements VectorMemoryInterface
{
    private readonly Client $client;
    private readonly EmbeddingServiceInterface $embeddingService;
    private readonly string $collectionName;

    public function __construct(EmbeddingServiceInterface $embeddingService, array $config = [])
    {
        $this->embeddingService = $embeddingService;
        $this->collectionName = $config['collection'] ?? 'phpswarm_memory';

        $this->client = new Client([
            'base_uri' => $config['base_url'] ?? 'http://localhost:8000',
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->initializeCollection();
    }

    private function initializeCollection(): void
    {
        try {
            $this->client->post('/api/v1/collections', [
                'json' => [
                    'name' => $this->collectionName,
                    'metadata' => ['description' => 'PHPSwarm vector memory'],
                ],
            ]);
        } catch (\Exception $e) {
            // Collection might already exist
        }
    }

    public function add(string $key, mixed $value, array $metadata = []): void
    {
        $this->addWithEmbedding($key, $value, $metadata);
    }

    public function addWithVector(string $key, mixed $value, array $vector, array $metadata = []): void
    {
        $this->client->post("/api/v1/collections/{$this->collectionName}/add", [
            'json' => [
                'ids' => [$key],
                'embeddings' => [$vector],
                'documents' => [is_string($value) ? $value : json_encode($value)],
                'metadatas' => [array_merge($metadata, ['_value' => serialize($value)])],
            ],
        ]);
    }

    public function addWithEmbedding(string $key, mixed $value, array $metadata = []): void
    {
        $text = is_string($value) ? $value : json_encode($value);
        $vector = $this->embeddingService->embed($text);
        $this->addWithVector($key, $value, $vector, $metadata);
    }

    public function semanticSearch(string $query, int $limit = 5, float $threshold = 0.0): array
    {
        $vector = $this->embeddingService->embed($query);
        return $this->vectorSearch($vector, $limit, $threshold);
    }

    public function vectorSearch(array $vector, int $limit = 5, float $threshold = 0.0): array
    {
        $response = $this->client->post("/api/v1/collections/{$this->collectionName}/query", [
            'json' => [
                'query_embeddings' => [$vector],
                'n_results' => $limit,
                'include' => ['metadatas', 'distances'],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $results = [];

        foreach ($data['ids'][0] ?? [] as $index => $id) {
            $distance = $data['distances'][0][$index] ?? 1.0;
            $similarity = 1.0 - $distance; // Convert distance to similarity

            if ($similarity >= $threshold) {
                $metadata = $data['metadatas'][0][$index] ?? [];
                $results[$id] = [
                    'value' => unserialize($metadata['_value'] ?? ''),
                    'score' => $similarity,
                    'metadata' => $metadata,
                ];
            }
        }

        return $results;
    }

    public function generateEmbedding(string $text): array
    {
        return $this->embeddingService->embed($text);
    }

    public function calculateSimilarity(array $vector1, array $vector2): float
    {
        $dotProduct = array_sum(array_map(fn($a, $b) => $a * $b, $vector1, $vector2));
        $magnitude1 = sqrt(array_sum(array_map(fn($x) => $x * $x, $vector1)));
        $magnitude2 = sqrt(array_sum(array_map(fn($x) => $x * $x, $vector2)));

        return $magnitude1 * $magnitude2 > 0 ? $dotProduct / ($magnitude1 * $magnitude2) : 0.0;
    }

    public function get(string $key): mixed
    {
        $response = $this->client->post("/api/v1/collections/{$this->collectionName}/get", [
            'json' => ['ids' => [$key], 'include' => ['metadatas']],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $metadata = $data['metadatas'][0] ?? null;

        return $metadata ? unserialize($metadata['_value'] ?? '') : null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): bool
    {
        $this->client->post("/api/v1/collections/{$this->collectionName}/delete", [
            'json' => ['ids' => [$key]],
        ]);
        return true;
    }

    public function search(string $query, int $limit = 5): array
    {
        return $this->semanticSearch($query, $limit);
    }

    public function clear(): void
    {
        $this->client->delete("/api/v1/collections/{$this->collectionName}");
        $this->initializeCollection();
    }

    public function all(): array
    {
        $response = $this->client->post("/api/v1/collections/{$this->collectionName}/get", [
            'json' => ['include' => ['metadatas']],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $results = [];

        foreach ($data['ids'] ?? [] as $index => $id) {
            $metadata = $data['metadatas'][$index] ?? [];
            $results[$id] = unserialize($metadata['_value'] ?? '');
        }

        return $results;
    }

    public function size(): int
    {
        $response = $this->client->get("/api/v1/collections/{$this->collectionName}");
        $data = json_decode($response->getBody()->getContents(), true);
        return $data['metadata']['count'] ?? 0;
    }

    public function getHistory(int $limit = 10, int $offset = 0): array
    {
        throw new MemoryException('Chroma does not support history tracking');
    }

    public function getVector(string $key): ?array
    {
        $response = $this->client->post("/api/v1/collections/{$this->collectionName}/get", [
            'json' => ['ids' => [$key], 'include' => ['embeddings']],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['embeddings'][0] ?? null;
    }

    public function findSimilar(string $key, int $limit = 5, float $threshold = 0.0): array
    {
        $vector = $this->getVector($key);
        if (!$vector) return [];

        $results = $this->vectorSearch($vector, $limit + 1, $threshold);
        unset($results[$key]);

        return array_slice($results, 0, $limit, true);
    }

    public function getVectorDimension(): int
    {
        return $this->embeddingService->getDimension();
    }

    public function getVectorStats(): array
    {
        return [
            'dimension' => $this->getVectorDimension(),
            'total_vectors' => $this->size(),
            'collection_name' => $this->collectionName,
        ];
    }
}
