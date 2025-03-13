<?php

declare(strict_types=1);

namespace PhpSwarm\Cache;

use PhpSwarm\Contract\Cache\CacheInterface;

/**
 * Simple array-based cache implementation for in-memory caching.
 */
class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expires: int|null}> Cache storage
     */
    private array $cache = [];

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->cache[$key]['value'];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expires = $ttl === null ? null : time() + $ttl;

        $this->cache[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $item = $this->cache[$key];

        // Check if item has expired
        if ($item['expires'] !== null && $item['expires'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function delete(string $key): bool
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Remove all expired items from the cache.
     *
     * @return int Number of items removed
     */
    public function cleanup(): int
    {
        $count = 0;
        $now = time();

        foreach ($this->cache as $key => $item) {
            if ($item['expires'] !== null && $item['expires'] < $now) {
                unset($this->cache[$key]);
                $count++;
            }
        }

        return $count;
    }
}
