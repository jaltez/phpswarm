<?php

declare(strict_types=1);

namespace PhpSwarm\Cache;

use PhpSwarm\Contract\Cache\CacheInterface;
use PhpSwarm\Exception\Cache\CacheException;
use Redis;
use RedisException;

/**
 * Redis-based cache implementation for distributed caching.
 */
class RedisCache implements CacheInterface
{
    /**
     * @var Redis The Redis client
     */
    private Redis $redis;

    /**
     * @var string The key prefix
     */
    private string $prefix;

    /**
     * Create a new RedisCache instance.
     *
     * @param array<string, mixed> $config Redis connection configuration
     * @throws CacheException If Redis connection fails
     */
    public function __construct(array $config = [])
    {
        $defaults = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 0.0,
            'database' => 0,
            'auth' => null,
            'prefix' => 'phpswarm:cache:',
        ];

        $config = array_merge($defaults, $config);
        $this->prefix = $config['prefix'];

        try {
            $this->redis = new Redis();

            if (!$this->redis->connect($config['host'], $config['port'], $config['timeout'])) {
                throw new CacheException('Failed to connect to Redis server');
            }

            if ($config['auth'] !== null) {
                if (!$this->redis->auth($config['auth'])) {
                    throw new CacheException('Failed to authenticate with Redis server');
                }
            }

            if ($config['database'] !== 0) {
                if (!$this->redis->select($config['database'])) {
                    throw new CacheException('Failed to select Redis database');
                }
            }
        } catch (RedisException $e) {
            throw new CacheException('Redis error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = $this->redis->get($this->prefix . $key);

            if ($value === false) {
                return $default;
            }

            return unserialize($value);
        } catch (RedisException $e) {
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $serialized = serialize($value);
            $prefixedKey = $this->prefix . $key;

            if ($ttl === null) {
                return $this->redis->set($prefixedKey, $serialized);
            }

            return $this->redis->setex($prefixedKey, $ttl, $serialized);
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        try {
            return $this->redis->exists($this->prefix . $key) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        try {
            return $this->redis->del($this->prefix . $key) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        try {
            $keys = $this->redis->keys($this->prefix . '*');

            if (empty($keys)) {
                return true;
            }

            return $this->redis->del($keys) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        try {
            $prefixedKeys = array_map(function ($key) {
                return $this->prefix . $key;
            }, $keys);

            $values = $this->redis->mGet($prefixedKeys);
            $result = [];

            foreach ($keys as $i => $key) {
                if ($values[$i] === false) {
                    $result[$key] = $default;
                } else {
                    $result[$key] = unserialize($values[$i]);
                }
            }

            return $result;
        } catch (RedisException $e) {
            $result = [];

            foreach ($keys as $key) {
                $result[$key] = $default;
            }

            return $result;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        try {
            $pipe = $this->redis->multi(Redis::PIPELINE);

            foreach ($values as $key => $value) {
                $serialized = serialize($value);
                $prefixedKey = $this->prefix . $key;

                if ($ttl === null) {
                    $pipe->set($prefixedKey, $serialized);
                } else {
                    $pipe->setex($prefixedKey, $ttl, $serialized);
                }
            }

            $pipe->exec();
            return true;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        try {
            $prefixedKeys = array_map(function ($key) {
                return $this->prefix . $key;
            }, $keys);

            return $this->redis->del($prefixedKeys) > 0;
        } catch (RedisException $e) {
            return false;
        }
    }

    /**
     * Get the underlying Redis instance.
     *
     * @return Redis
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }
}
