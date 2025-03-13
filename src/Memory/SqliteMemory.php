<?php

declare(strict_types=1);

namespace PhpSwarm\Memory;

use PDO;
use PDOException;
use DateTimeImmutable;
use PhpSwarm\Contract\Memory\MemoryInterface;
use PhpSwarm\Exception\Memory\MemoryException;

/**
 * SQLite-based implementation of the Memory interface.
 *
 * Provides persistent memory storage using SQLite database.
 */
class SqliteMemory implements MemoryInterface
{
    /**
     * @var PDO The SQLite database connection
     */
    private PDO $db;

    /**
     * @var string The memory table name
     */
    private readonly string $tableName;

    /**
     * @var int|null The TTL (time-to-live) in seconds for memory entries
     */
    private readonly ?int $ttl;

    /**
     * Create a new SqliteMemory instance.
     *
     * @param array<string, mixed> $config Configuration options
     * @throws MemoryException If there's an error connecting to the database
     */
    public function __construct(array $config = [])
    {
        // Get configuration options
        $dbPath = $config['db_path'] ?? ':memory:';
        $this->tableName = $config['table_name'] ?? 'memory';
        $this->ttl = $config['ttl'] ?? null;

        try {
            // Create database connection
            $this->db = new PDO("sqlite:{$dbPath}");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create the memory table if it doesn't exist
            $this->initializeDatabase();
        } catch (PDOException $e) {
            throw new MemoryException('Failed to connect to SQLite database: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Initialize the database and create necessary tables.
     *
     * @throws PDOException If there's an error creating the tables
     */
    private function initializeDatabase(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                key TEXT PRIMARY KEY,
                value BLOB,
                metadata TEXT,
                timestamp TEXT
            )
        ");

        // Create an index on the timestamp for faster history queries
        $this->db->exec("
            CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_timestamp 
            ON {$this->tableName} (timestamp)
        ");
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function add(string $key, mixed $value, array $metadata = []): void
    {
        $timestamp = new DateTimeImmutable();

        // Convert the value to a serialized string
        $serializedValue = serialize($value);
        $serializedMetadata = json_encode($metadata) ?: '{}';

        try {
            $stmt = $this->db->prepare("
                INSERT OR REPLACE INTO {$this->tableName}
                (key, value, metadata, timestamp)
                VALUES (:key, :value, :metadata, :timestamp)
            ");

            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $serializedValue);
            $stmt->bindParam(':metadata', $serializedMetadata);
            $formattedTimestamp = $timestamp->format('Y-m-d H:i:s.u');
            $stmt->bindParam(':timestamp', $formattedTimestamp);

            $stmt->execute();

            // Clean up expired entries if TTL is set
            if ($this->ttl !== null && $this->ttl > 0) {
                $this->cleanExpiredEntries();
            }
        } catch (PDOException $e) {
            throw new MemoryException('Failed to add memory entry: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function get(string $key): mixed
    {
        try {
            $stmt = $this->db->prepare("
                SELECT value, timestamp FROM {$this->tableName}
                WHERE key = :key
            ");

            $stmt->bindParam(':key', $key);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                return null;
            }

            // Check if entry has expired
            if ($this->isExpired($result['timestamp'])) {
                $this->delete($key);
                return null;
            }

            return unserialize($result['value']);
        } catch (PDOException $e) {
            throw new MemoryException('Failed to retrieve memory entry: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function has(string $key): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT timestamp FROM {$this->tableName}
                WHERE key = :key
            ");

            $stmt->bindParam(':key', $key);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                return false;
            }

            // Check if entry has expired
            if ($this->isExpired($result['timestamp'])) {
                $this->delete($key);
                return false;
            }

            return true;
        } catch (PDOException $e) {
            throw new MemoryException('Failed to check key existence: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function delete(string $key): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM {$this->tableName}
                WHERE key = :key
            ");

            $stmt->bindParam(':key', $key);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new MemoryException('Failed to delete memory entry: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function search(string $query, int $limit = 5): array
    {
        try {
            // Simple search using LIKE
            $searchPattern = '%' . $query . '%';

            $stmt = $this->db->prepare("
                SELECT key, value, timestamp FROM {$this->tableName}
                WHERE key LIKE :query OR value LIKE :query
                ORDER BY timestamp DESC
                LIMIT :limit
            ");

            $stmt->bindParam(':query', $searchPattern);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Skip expired entries
                if ($this->isExpired($row['timestamp'])) {
                    continue;
                }

                $results[$row['key']] = unserialize($row['value']);
            }

            return $results;
        } catch (PDOException $e) {
            throw new MemoryException('Failed to search memory: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function clear(): void
    {
        try {
            $this->db->exec("DELETE FROM {$this->tableName}");
        } catch (PDOException $e) {
            throw new MemoryException('Failed to clear memory: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function all(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT key, value, timestamp FROM {$this->tableName}
                ORDER BY timestamp DESC
            ");

            $stmt->execute();

            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Skip expired entries
                if ($this->isExpired($row['timestamp'])) {
                    continue;
                }

                $results[$row['key']] = unserialize($row['value']);
            }

            return $results;
        } catch (PDOException $e) {
            throw new MemoryException('Failed to retrieve all memory entries: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function size(): int
    {
        try {
            // Clean expired entries first if TTL is set
            if ($this->ttl !== null && $this->ttl > 0) {
                $this->cleanExpiredEntries();
            }

            $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->tableName}");
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new MemoryException('Failed to get memory size: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getHistory(int $limit = 10, int $offset = 0): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT key, value, metadata, timestamp 
                FROM {$this->tableName}
                ORDER BY timestamp DESC
                LIMIT :limit OFFSET :offset
            ");

            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $history = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Skip expired entries
                if ($this->isExpired($row['timestamp'])) {
                    continue;
                }

                $timestamp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $row['timestamp'])
                    ?: new DateTimeImmutable($row['timestamp']);

                $history[$row['key']] = [
                    'value' => unserialize($row['value']),
                    'metadata' => json_decode((string) $row['metadata'], true) ?: [],
                    'timestamp' => $timestamp,
                ];
            }

            return $history;
        } catch (PDOException $e) {
            throw new MemoryException('Failed to retrieve memory history: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get metadata for a specific key.
     *
     * @param string $key The key to get metadata for
     * @return array<string, mixed>|null The metadata or null if not found
     * @throws MemoryException If there's an error retrieving the metadata
     */
    public function getMetadata(string $key): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT metadata, timestamp FROM {$this->tableName}
                WHERE key = :key
            ");

            $stmt->bindParam(':key', $key);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                return null;
            }

            // Check if entry has expired
            if ($this->isExpired($result['timestamp'])) {
                $this->delete($key);
                return null;
            }

            return json_decode((string) $result['metadata'], true) ?: [];
        } catch (PDOException $e) {
            throw new MemoryException('Failed to retrieve metadata: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the timestamp for a specific key.
     *
     * @param string $key The key to get the timestamp for
     * @return DateTimeImmutable|null The timestamp or null if not found
     * @throws MemoryException If there's an error retrieving the timestamp
     */
    public function getTimestamp(string $key): ?DateTimeImmutable
    {
        try {
            $stmt = $this->db->prepare("
                SELECT timestamp FROM {$this->tableName}
                WHERE key = :key
            ");

            $stmt->bindParam(':key', $key);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                return null;
            }

            // Check if entry has expired
            if ($this->isExpired($result['timestamp'])) {
                $this->delete($key);
                return null;
            }

            return DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $result['timestamp'])
                ?: new DateTimeImmutable($result['timestamp']);
        } catch (PDOException $e) {
            throw new MemoryException('Failed to retrieve timestamp: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if an entry has expired based on its timestamp.
     *
     * @param string $timestamp The timestamp to check
     * @return bool Whether the entry has expired
     */
    private function isExpired(string $timestamp): bool
    {
        if ($this->ttl === null || $this->ttl <= 0) {
            return false;
        }

        $entryTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $timestamp)
            ?: new DateTimeImmutable($timestamp);

        $expirationTime = $entryTime->getTimestamp() + $this->ttl;
        return time() > $expirationTime;
    }

    /**
     * Clean up expired entries.
     *
     * @throws PDOException If there's an error cleaning up expired entries
     */
    private function cleanExpiredEntries(): void
    {
        if ($this->ttl === null || $this->ttl <= 0) {
            return;
        }

        $expirationThreshold = (new DateTimeImmutable())
            ->modify("-{$this->ttl} seconds")
            ->format('Y-m-d H:i:s.u');

        $stmt = $this->db->prepare("
            DELETE FROM {$this->tableName}
            WHERE timestamp < :threshold
        ");

        $stmt->bindParam(':threshold', $expirationThreshold);
        $stmt->execute();
    }
}
