<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Cache;

/**
 * Interface for cache implementations.
 */
interface CacheInterface
{
    /**
     * Get a value from the cache.
     *
     * @param string $key The cache key
     * @param mixed $default Default value to return if key doesn't exist
     * @return mixed The cached value or default if not found
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a value in the cache.
     *
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int|null $ttl Time to live in seconds; null for permanent storage
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Check if a key exists in the cache.
     *
     * @param string $key The cache key
     * @return bool True if the key exists, false otherwise
     */
    public function has(string $key): bool;

    /**
     * Delete a value from the cache.
     *
     * @param string $key The cache key
     * @return bool True if the key was deleted, false otherwise
     */
    public function delete(string $key): bool;

    /**
     * Clear all values from the cache.
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;

    /**
     * Get multiple values from the cache.
     *
     * @param array<string> $keys List of cache keys
     * @param mixed $default Default value for keys that don't exist
     * @return array<string, mixed> Associative array of key => value pairs
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * Set multiple values in the cache.
     *
     * @param array<string, mixed> $values Associative array of key => value pairs
     * @param int|null $ttl Time to live in seconds; null for permanent storage
     * @return bool True on success, false on failure
     */
    public function setMultiple(array $values, ?int $ttl = null): bool;

    /**
     * Delete multiple values from the cache.
     *
     * @param array<string> $keys List of cache keys
     * @return bool True if all keys were deleted, false otherwise
     */
    public function deleteMultiple(array $keys): bool;
}
