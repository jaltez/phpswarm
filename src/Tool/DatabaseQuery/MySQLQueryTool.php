<?php

declare(strict_types=1);

namespace PhpSwarm\Tool\DatabaseQuery;

use PhpSwarm\Exception\Tool\ToolExecutionException;
use PhpSwarm\Tool\BaseTool;
use PDO;
use PDOException;

/**
 * A tool specifically for executing MySQL database queries to retrieve or modify information in a MySQL database.
 */
class MySQLQueryTool extends BaseTool
{
    /**
     * @var PDO|null The database connection
     */
    private ?PDO $connection = null;
    
    /**
     * @var array<string, mixed> The database configuration
     */
    private array $config;
    
    /**
     * Create a new MySQLQueryTool instance.
     * 
     * @param array<string, mixed> $config The MySQL database configuration
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'mysql_query',
            'Execute MySQL queries to retrieve or modify information in a MySQL database'
        );
        
        $this->parametersSchema = [
            'query' => [
                'type' => 'string',
                'description' => 'The SQL query to execute',
                'required' => true,
            ],
            'params' => [
                'type' => 'array',
                'description' => 'The parameters to bind to the query',
                'required' => false,
                'default' => [],
            ],
            'fetch_mode' => [
                'type' => 'string',
                'description' => 'The fetch mode for SELECT queries (all, one, column, value)',
                'required' => false,
                'default' => 'all',
                'enum' => ['all', 'one', 'column', 'value'],
            ],
        ];
        
        $this->config = array_merge([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ], $config);
        
        $this->addTag('database');
        $this->addTag('sql');
        $this->addTag('mysql');
        $this->addTag('query');
        $this->setRequiresAuthentication(true);
    }
    
    /**
     * {@inheritdoc}
     */
    public function run(array $parameters = []): mixed
    {
        $this->validateParameters($parameters);
        
        $query = $parameters['query'];
        $params = $parameters['params'] ?? [];
        $fetchMode = $parameters['fetch_mode'] ?? 'all';
        
        try {
            // Connect to the database if not already connected
            if (!$this->connection) {
                $this->connect();
            }
            
            // Check if the query is a SELECT query
            $isSelect = $this->isSelectQuery($query);
            
            // Prepare and execute the query
            $statement = $this->connection->prepare($query);
            $statement->execute($params);
            
            // Return appropriate result based on query type
            if ($isSelect) {
                return match ($fetchMode) {
                    'one' => $statement->fetch(),
                    'column' => $statement->fetchColumn(),
                    'value' => $statement->fetchColumn(),
                    default => $statement->fetchAll(),
                };
            } else {
                // For non-SELECT queries, return affected rows
                return [
                    'affected_rows' => $statement->rowCount(),
                    'last_insert_id' => $this->connection->lastInsertId() ?: null,
                ];
            }
        } catch (PDOException $e) {
            throw new ToolExecutionException(
                "Database query failed: {$e->getMessage()}",
                $parameters,
                $this->getName(),
                0,
                $e
            );
        } catch (\Throwable $e) {
            throw new ToolExecutionException(
                "Failed to execute query: {$e->getMessage()}",
                $parameters,
                $this->getName(),
                0,
                $e
            );
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        try {
            // Check if the required credentials are provided
            if (empty($this->config['database']) || 
                empty($this->config['username'])) {
                return false;
            }
            
            // Try to connect to the database
            $this->connect();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
    
    /**
     * Connect to the database.
     * 
     * @return void
     * @throws PDOException If connection fails
     */
    private function connect(): void
    {
        $dsn = $this->buildDsn();
        
        $this->connection = new PDO(
            $dsn,
            $this->config['username'],
            $this->config['password'],
            $this->config['options'] ?? []
        );
    }
    
    /**
     * Build the DSN string for the PDO connection based on the driver.
     * 
     * @return string
     */
    private function buildDsn(): string
    {
        $driver = $this->config['driver'];
        
        return match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            ),
            default => throw new ToolExecutionException(
                "Unsupported database driver: {$driver}",
                $this->config,
                $this->getName()
            ),
        };
    }
    
    /**
     * Check if a query is a SELECT query.
     * 
     * @param string $query The SQL query
     * @return bool
     */
    private function isSelectQuery(string $query): bool
    {
        $query = trim($query);
        return stripos($query, 'SELECT') === 0 || 
               stripos($query, 'SHOW') === 0 || 
               stripos($query, 'DESCRIBE') === 0 || 
               stripos($query, 'EXPLAIN') === 0;
    }
    
    /**
     * Close the database connection.
     * 
     * @return void
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }
    
    /**
     * Set the database configuration.
     * 
     * @param array<string, mixed> $config The database configuration
     * @return self
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
} 