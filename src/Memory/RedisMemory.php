<?php

declare(strict_types=1);

namespace PhpSwarm\Memory;

use PhpSwarm\Contract\Memory\MemoryInterface;
use PhpSwarm\Exception\Memory\MemoryException;

/**
 * A Redis-based implementation of the Memory interface.
 */
class RedisMemory implements MemoryInterface
{
    /**
     * @var \Redis The Redis client
     */
    private \Redis $redis;
    
    /**
     * @var string Prefix for all keys stored in Redis
     */
    private string $prefix;
    
    /**
     * @var int Time-to-live in seconds (0 = no expiration)
     */
    private int $ttl;
    
    /**
     * Create a new RedisMemory instance.
     *
     * @param array<string, mixed> $config Configuration options
     * @throws MemoryException If Redis connection fails
     */
    public function __construct(array $config = [])
    {
        $this->prefix = $config['prefix'] ?? 'phpswarm:';
        $this->ttl = $config['ttl'] ?? 3600; // Default to 1 hour
        
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 6379;
        $database = $config['database'] ?? 0;
        $password = $config['password'] ?? null;
        
        $this->redis = new \Redis();
        
        try {
            if (!$this->redis->connect($host, $port)) {
                throw new MemoryException("Failed to connect to Redis at $host:$port");
            }
            
            if ($password !== null) {
                if (!$this->redis->auth($password)) {
                    throw new MemoryException('Redis authentication failed');
                }
            }
            
            if (!$this->redis->select($database)) {
                throw new MemoryException("Failed to select Redis database $database");
            }
        } catch (\RedisException $e) {
            throw new MemoryException('Redis error: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function add(string $key, mixed $value, array $metadata = []): void
    {
        $data = [
            'value' => $value,
            'metadata' => $metadata,
            'timestamp' => time(),
        ];
        
        $serialized = serialize($data);
        $prefixedKey = $this->prefixKey($key);
        
        try {
            if ($this->ttl > 0) {
                $this->redis->setex($prefixedKey, $this->ttl, $serialized);
            } else {
                $this->redis->set($prefixedKey, $serialized);
            }
            
            // Add key to index
            $this->redis->sAdd($this->prefixKey('_index'), $key);
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to add memory: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        try {
            $serialized = $this->redis->get($this->prefixKey($key));
            
            if ($serialized === false) {
                return null;
            }
            
            $data = unserialize($serialized);
            
            return $data['value'] ?? null;
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to get memory: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        try {
            return (bool) $this->redis->exists($this->prefixKey($key));
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to check memory: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            $prefixedKey = $this->prefixKey($key);
            $result = $this->redis->del($prefixedKey) > 0;
            
            if ($result) {
                // Remove from index
                $this->redis->sRem($this->prefixKey('_index'), $key);
            }
            
            return $result;
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to delete memory: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function search(string $query, int $limit = 5): array
    {
        try {
            $results = [];
            $count = 0;
            $keys = $this->redis->sMembers($this->prefixKey('_index'));
            
            // Very basic search - iterate through all keys and check if the serialized data contains the query
            foreach ($keys as $key) {
                $serialized = $this->redis->get($this->prefixKey($key));
                
                if ($serialized !== false && str_contains($serialized, $query)) {
                    $data = unserialize($serialized);
                    $results[$key] = $data['value'] ?? null;
                    $count++;
                    
                    if ($count >= $limit) {
                        break;
                    }
                }
            }
            
            return $results;
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to search memory: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        try {
            $keys = $this->redis->sMembers($this->prefixKey('_index'));
            
            // Delete all memory keys
            foreach ($keys as $key) {
                $this->redis->del($this->prefixKey($key));
            }
            
            // Clear the index
            $this->redis->del($this->prefixKey('_index'));
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to clear memory: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        try {
            $keys = $this->redis->sMembers($this->prefixKey('_index'));
            $result = [];
            
            foreach ($keys as $key) {
                $serialized = $this->redis->get($this->prefixKey($key));
                
                if ($serialized !== false) {
                    $data = unserialize($serialized);
                    $result[$key] = $data['value'] ?? null;
                }
            }
            
            return $result;
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to get all memories: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function size(): int
    {
        try {
            return $this->redis->sCard($this->prefixKey('_index'));
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to get memory size: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getHistory(int $limit = 10, int $offset = 0): array
    {
        try {
            $keys = $this->redis->sMembers($this->prefixKey('_index'));
            $allData = [];
            
            // Fetch all entries with their timestamps
            foreach ($keys as $key) {
                $serialized = $this->redis->get($this->prefixKey($key));
                
                if ($serialized !== false) {
                    $data = unserialize($serialized);
                    $timestamp = $data['timestamp'] ?? 0;
                    $allData[] = [
                        'key' => $key,
                        'value' => $data['value'] ?? null,
                        'metadata' => $data['metadata'] ?? [],
                        'timestamp' => $timestamp,
                    ];
                }
            }
            
            // Sort by timestamp in descending order (newest first)
            usort($allData, function ($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });
            
            // Apply offset and limit
            $allData = array_slice($allData, $offset, $limit);
            
            // Reformat to expected structure
            $result = [];
            foreach ($allData as $item) {
                $key = $item['key'];
                $result[$key] = [
                    'value' => $item['value'],
                    'metadata' => $item['metadata'],
                    'timestamp' => new \DateTimeImmutable('@' . $item['timestamp']),
                ];
            }
            
            return $result;
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to get memory history: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get the metadata for a specific key.
     *
     * @param string $key
     * @return array<string, mixed>|null
     */
    public function getMetadata(string $key): ?array
    {
        try {
            $serialized = $this->redis->get($this->prefixKey($key));
            
            if ($serialized === false) {
                return null;
            }
            
            $data = unserialize($serialized);
            
            return $data['metadata'] ?? null;
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to get memory metadata: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get the timestamp for a specific key.
     *
     * @param string $key
     * @return \DateTimeImmutable|null
     */
    public function getTimestamp(string $key): ?\DateTimeImmutable
    {
        try {
            $serialized = $this->redis->get($this->prefixKey($key));
            
            if ($serialized === false) {
                return null;
            }
            
            $data = unserialize($serialized);
            $timestamp = $data['timestamp'] ?? null;
            
            return $timestamp !== null ? new \DateTimeImmutable('@' . $timestamp) : null;
        } catch (\RedisException $e) {
            throw new MemoryException('Failed to get memory timestamp: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Prefix a key with the Redis namespace.
     *
     * @param string $key
     * @return string
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }
} 