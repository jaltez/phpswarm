<?php

declare(strict_types=1);

namespace PhpSwarm\Memory;

use PhpSwarm\Contract\Memory\VectorMemoryInterface;
use PhpSwarm\Contract\Utility\EmbeddingServiceInterface;
use PhpSwarm\Exception\Memory\MemoryException;

/**
 * Simple in-memory vector memory implementation.
 */
class VectorMemory implements VectorMemoryInterface
{
    private array $vectors = [];
    private array $storage = [];
    private array $metadata = [];
    private array $timestamps = [];
    private EmbeddingServiceInterface $embeddingService;

    public function __construct(EmbeddingServiceInterface $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    public function add(string $key, mixed $value, array $metadata = []): void
    {
        $this->addWithEmbedding($key, $value, $metadata);
    }

    public function addWithVector(string $key, mixed $value, array $vector, array $metadata = []): void
    {
        $this->storage[$key] = $value;
        $this->vectors[$key] = $vector;
        $this->metadata[$key] = $metadata;
        $this->timestamps[$key] = new \DateTimeImmutable();
    }

    public function addWithEmbedding(string $key, mixed $value, array $metadata = []): void
    {
        $text = is_string($value) ? $value : json_encode($value);
        $vector = $this->embeddingService->embed($text);
        $this->addWithVector($key, $value, $vector, $metadata);
    }

    public function semanticSearch(string $query, int $limit = 5, float $threshold = 0.0): array
    {
        $queryVector = $this->embeddingService->embed($query);
        return $this->vectorSearch($queryVector, $limit, $threshold);
    }

    public function vectorSearch(array $vector, int $limit = 5, float $threshold = 0.0): array
    {
        $results = [];

        foreach ($this->vectors as $key => $storedVector) {
            $similarity = $this->calculateSimilarity($vector, $storedVector);

            if ($similarity >= $threshold) {
                $results[$key] = [
                    'value' => $this->storage[$key],
                    'score' => $similarity,
                    'metadata' => $this->metadata[$key] ?? []
                ];
            }
        }

        // Sort by similarity score (highest first)
        uasort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit, true);
    }

    public function getVector(string $key): ?array
    {
        return $this->vectors[$key] ?? null;
    }

    public function generateEmbedding(string $text): array
    {
        return $this->embeddingService->embed($text);
    }

    public function calculateSimilarity(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            throw new MemoryException('Vector dimensions must match');
        }

        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        return ($magnitude1 * $magnitude2) > 0 ? $dotProduct / ($magnitude1 * $magnitude2) : 0.0;
    }

    public function findSimilar(string $key, int $limit = 5, float $threshold = 0.0): array
    {
        $vector = $this->getVector($key);
        if ($vector === null) {
            return [];
        }

        $results = $this->vectorSearch($vector, $limit + 1, $threshold);
        unset($results[$key]); // Remove the original key

        return array_slice($results, 0, $limit, true);
    }

    public function getVectorDimension(): int
    {
        return $this->embeddingService->getDimension();
    }

    public function getVectorStats(): array
    {
        return [
            'total_vectors' => count($this->vectors),
            'dimension' => $this->getVectorDimension(),
            'memory_usage' => memory_get_usage(),
        ];
    }

    // Basic MemoryInterface methods
    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    public function delete(string $key): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        unset($this->storage[$key], $this->vectors[$key], $this->metadata[$key], $this->timestamps[$key]);
        return true;
    }

    public function search(string $query, int $limit = 5): array
    {
        return $this->semanticSearch($query, $limit);
    }

    public function clear(): void
    {
        $this->storage = [];
        $this->vectors = [];
        $this->metadata = [];
        $this->timestamps = [];
    }

    public function all(): array
    {
        return $this->storage;
    }

    public function size(): int
    {
        return count($this->storage);
    }

    public function getHistory(int $limit = 10, int $offset = 0): array
    {
        $keys = array_keys($this->timestamps);
        usort($keys, fn($a, $b) => $this->timestamps[$b] <=> $this->timestamps[$a]);

        $keys = array_slice($keys, $offset, $limit);
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = [
                'value' => $this->storage[$key],
                'metadata' => $this->metadata[$key],
                'timestamp' => $this->timestamps[$key],
            ];
        }

        return $result;
    }
}
