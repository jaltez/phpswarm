<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Utility;

/**
 * Interface for embedding generation services.
 *
 * Embedding services convert text into numerical vector representations
 * that can be used for semantic similarity and vector search operations.
 */
interface EmbeddingServiceInterface
{
    /**
     * Generate embeddings for a single text.
     *
     * @param string $text The text to generate embeddings for
     * @return array<float> The embedding vector
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts in batch.
     *
     * @param array<string> $texts The texts to generate embeddings for
     * @return array<array<float>> Array of embedding vectors
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the dimension of the embeddings produced by this service.
     *
     * @return int The embedding dimension
     */
    public function getDimension(): int;

    /**
     * Get the maximum length of text that can be embedded at once.
     *
     * @return int The maximum text length
     */
    public function getMaxTextLength(): int;

    /**
     * Get the model name used by this embedding service.
     *
     * @return string The model name
     */
    public function getModelName(): string;
}
