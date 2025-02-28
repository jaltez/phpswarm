<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Memory;

/**
 * Interface for memory storage systems.
 *
 * Memory allows agents to remember past interactions and maintain
 * context over time, enabling more coherent and contextual responses.
 */
interface MemoryInterface
{
    /**
     * Add a memory entry with the given key and value.
     *
     * @param string $key The key for the memory
     * @param mixed $value The value to store
     * @param array<string, mixed> $metadata Additional metadata for the memory
     * @return void
     */
    public function add(string $key, mixed $value, array $metadata = []): void;

    /**
     * Get a memory entry by key.
     *
     * @param string $key The key to retrieve
     * @return mixed The stored value or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Check if a memory entry exists.
     *
     * @param string $key The key to check
     * @return bool Whether the key exists
     */
    public function has(string $key): bool;

    /**
     * Delete a memory entry.
     *
     * @param string $key The key to delete
     * @return bool Whether the deletion was successful
     */
    public function delete(string $key): bool;

    /**
     * Search for memories that match the given query.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results to return
     * @return array<mixed> The search results
     */
    public function search(string $query, int $limit = 5): array;

    /**
     * Clear all memories.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Get all memories.
     *
     * @return array<mixed>
     */
    public function all(): array;

    /**
     * Get the size of the memory storage.
     *
     * @return int
     */
    public function size(): int;

    /**
     * Get memory entries in chronological order.
     *
     * @param int $limit Maximum number of entries to return
     * @param int $offset Offset to start from
     * @return array<mixed>
     */
    public function getHistory(int $limit = 10, int $offset = 0): array;
}
