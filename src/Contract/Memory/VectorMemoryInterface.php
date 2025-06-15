<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Memory;

/**
 * Interface for vector-based memory storage systems.
 *
 * Vector memory enables semantic search and similarity-based retrieval
 * using embeddings and vector databases.
 */
interface VectorMemoryInterface extends MemoryInterface
{
    /**
     * Add a memory entry with its vector representation.
     */
    public function addWithVector(string $key, mixed $value, array $vector, array $metadata = []): void;

    /**
     * Add a memory entry and automatically generate its vector representation.
     */
    public function addWithEmbedding(string $key, mixed $value, array $metadata = []): void;

    /**
     * Search for memories using semantic similarity.
     */
    public function semanticSearch(string $query, int $limit = 5, float $threshold = 0.0): array;

    /**
     * Search for memories using a vector directly.
     */
    public function vectorSearch(array $vector, int $limit = 5, float $threshold = 0.0): array;

    /**
     * Get the vector representation of a stored memory.
     */
    public function getVector(string $key): ?array;

    /**
     * Generate embeddings for the given text.
     */
    public function generateEmbedding(string $text): array;

    /**
     * Calculate similarity between two vectors.
     */
    public function calculateSimilarity(array $vector1, array $vector2): float;

    /**
     * Get similar memories to a given key.
     */
    public function findSimilar(string $key, int $limit = 5, float $threshold = 0.0): array;

    /**
     * Get the dimension of the vectors used by this memory store.
     */
    public function getVectorDimension(): int;

    /**
     * Get statistics about the vector memory store.
     */
    public function getVectorStats(): array;
}
