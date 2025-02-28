<?php

declare(strict_types=1);

namespace PhpSwarm\Memory;

use PhpSwarm\Contract\Memory\MemoryInterface;

/**
 * A simple in-memory implementation of the Memory interface using PHP arrays.
 */
class ArrayMemory implements MemoryInterface
{
    /**
     * @var array<string, mixed> The memory storage
     */
    private array $storage = [];

    /**
     * @var array<string, array<string, mixed>> Metadata storage
     */
    private array $metadata = [];

    /**
     * @var array<string, \DateTimeImmutable> Timestamps for each memory
     */
    private array $timestamps = [];

    /**
     * {@inheritdoc}
     */
    public function add(string $key, mixed $value, array $metadata = []): void
    {
        $this->storage[$key] = $value;
        $this->metadata[$key] = $metadata;
        $this->timestamps[$key] = new \DateTimeImmutable();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        unset($this->storage[$key], $this->metadata[$key], $this->timestamps[$key]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $query, int $limit = 5): array
    {
        $results = [];
        $count = 0;

        foreach ($this->storage as $key => $value) {
            // Very simple search implementation - just checks if the key or value contains the query
            $valueString = is_string($value) ? $value : json_encode($value);
            if (
                str_contains($key, $query) ||
                (is_string($valueString) && str_contains($valueString, $query))
            ) {
                $results[$key] = $value;
                $count++;

                if ($count >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->storage = [];
        $this->metadata = [];
        $this->timestamps = [];
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->storage;
    }

    /**
     * {@inheritdoc}
     */
    public function size(): int
    {
        return count($this->storage);
    }

    /**
     * {@inheritdoc}
     */
    public function getHistory(int $limit = 10, int $offset = 0): array
    {
        // Sort by timestamp in descending order (newest first)
        $keys = array_keys($this->timestamps);
        usort($keys, function ($a, $b) {
            return $this->timestamps[$b] <=> $this->timestamps[$a];
        });

        // Apply offset and limit
        $keys = array_slice($keys, $offset, $limit);

        // Build the result array
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

    /**
     * Get the metadata for a specific key.
     *
     * @param string $key
     * @return array<string, mixed>|null
     */
    public function getMetadata(string $key): ?array
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Get the timestamp for a specific key.
     *
     * @param string $key
     * @return \DateTimeImmutable|null
     */
    public function getTimestamp(string $key): ?\DateTimeImmutable
    {
        return $this->timestamps[$key] ?? null;
    }
}
