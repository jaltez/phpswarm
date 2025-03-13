<?php

declare(strict_types=1);

namespace PhpSwarm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to scaffold a new Memory provider class
 */
class MakeMemoryCommand extends Command
{
    /**
     * Configure the command
     */
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('make:memory')
            ->setDescription('Create a new Memory provider class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the memory provider class')
            ->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory to create the memory provider in', 'src/Memory')
            ->addOption('persistent', 'p', InputOption::VALUE_NONE, 'Whether the memory provider is persistent');
    }

    /**
     * Execute the command
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $directory = $input->getOption('directory');
        $isPersistent = $input->getOption('persistent');

        // Ensure the name has the correct format
        $className = $this->formatClassName($name);

        // Create the directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate the file path
        $filePath = $directory . '/' . $className . '.php';

        // Check if the file already exists
        if (file_exists($filePath)) {
            $io->error(sprintf('Memory provider "%s" already exists at "%s"', $className, $filePath));
            return Command::FAILURE;
        }

        // Generate the namespace based on the directory
        $namespace = $this->generateNamespace($directory);

        // Generate the memory provider class content
        $content = $this->generateMemoryClass($namespace, $className, $isPersistent);

        // Write the content to the file
        file_put_contents($filePath, $content);

        $io->success(sprintf('Memory provider "%s" created successfully at "%s"', $className, $filePath));

        return Command::SUCCESS;
    }

    /**
     * Format the class name to ensure it follows PHP conventions
     */
    private function formatClassName(string $name): string
    {
        // Remove "Memory" suffix if present, we'll add it back later
        $name = preg_replace('/Memory$/', '', $name);

        // Convert to PascalCase
        $name = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));

        // Add "Memory" suffix
        return $name . 'Memory';
    }

    /**
     * Generate the namespace based on the directory
     */
    private function generateNamespace(string $directory): string
    {
        // Convert directory path to namespace
        $namespace = str_replace('/', '\\', $directory);

        // Remove src/ or src\ prefix
        $namespace = preg_replace('/^src[\/\\\\]/', '', $namespace);

        // Add PhpSwarm prefix
        return 'PhpSwarm\\' . $namespace;
    }

    /**
     * Generate the memory provider class content
     */
    private function generateMemoryClass(
        string $namespace,
        string $className,
        bool $isPersistent
    ): string {
        $persistentCode = $isPersistent ? $this->generatePersistentMemoryCode() : '';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use PhpSwarm\Contract\Memory\MemoryInterface;
use PhpSwarm\Exception\Memory\MemoryException;
use DateTimeImmutable;

/**
 * {$className} - Custom memory provider implementation
 */
class {$className} implements MemoryInterface
{
    /**
     * @var array<string, array{value: mixed, metadata: array<string, mixed>, timestamp: string}> The memory storage
     */
    private array \$storage = [];
    
    /**
     * @var int|null The time-to-live for memory entries in seconds, or null for no expiration
     */
    private ?int \$ttl;{$persistentCode}
    
    /**
     * Create a new {$className} instance
     *
     * @param array<string, mixed> \$config Configuration options
     */
    public function __construct(array \$config = [])
    {
        \$this->ttl = \$config['ttl'] ?? null;
        
        // Initialize the memory provider
        \$this->initialize(\$config);
    }
    
    /**
     * Initialize the memory provider with configuration
     *
     * @param array<string, mixed> \$config Configuration options
     * @return void
     */
    protected function initialize(array \$config): void
    {
        // Custom initialization logic here
    }
    
    /**
     * Add a memory entry with the given key and value
     *
     * @param string \$key The key for the memory
     * @param mixed \$value The value to store
     * @param array<string, mixed> \$metadata Additional metadata for the memory
     * @return void
     */
    public function add(string \$key, mixed \$value, array \$metadata = []): void
    {
        \$this->storage[\$key] = [
            'value' => \$value,
            'metadata' => \$metadata,
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Get a memory entry by key
     *
     * @param string \$key The key to retrieve
     * @return mixed The stored value or null if not found
     */
    public function get(string \$key): mixed
    {
        if (!isset(\$this->storage[\$key])) {
            return null;
        }
        
        // Check if the entry has expired
        if (\$this->isExpired(\$key)) {
            \$this->delete(\$key);
            return null;
        }
        
        return \$this->storage[\$key]['value'];
    }
    
    /**
     * Check if a memory entry exists
     *
     * @param string \$key The key to check
     * @return bool Whether the key exists
     */
    public function has(string \$key): bool
    {
        if (!isset(\$this->storage[\$key])) {
            return false;
        }
        
        // Check if the entry has expired
        if (\$this->isExpired(\$key)) {
            \$this->delete(\$key);
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete a memory entry
     *
     * @param string \$key The key to delete
     * @return bool Whether the deletion was successful
     */
    public function delete(string \$key): bool
    {
        if (!isset(\$this->storage[\$key])) {
            return false;
        }
        
        unset(\$this->storage[\$key]);
        return true;
    }
    
    /**
     * Search for memories that match the given query
     *
     * @param string \$query The search query
     * @param int \$limit Maximum number of results to return
     * @return array<mixed> The search results
     */
    public function search(string \$query, int \$limit = 5): array
    {
        \$results = [];
        \$count = 0;
        
        // Simple search implementation - override with more sophisticated search if needed
        foreach (\$this->storage as \$key => \$data) {
            // Skip expired entries
            if (\$this->isExpired(\$key)) {
                continue;
            }
            
            \$value = \$data['value'];
            \$stringValue = \$this->valueToString(\$value);
            
            if (stripos(\$key, \$query) !== false || stripos(\$stringValue, \$query) !== false) {
                \$results[\$key] = \$value;
                \$count++;
                
                if (\$count >= \$limit) {
                    break;
                }
            }
        }
        
        return \$results;
    }
    
    /**
     * Clear all memories
     *
     * @return void
     */
    public function clear(): void
    {
        \$this->storage = [];
    }
    
    /**
     * Get all memories
     *
     * @return array<mixed>
     */
    public function all(): array
    {
        \$result = [];
        
        foreach (\$this->storage as \$key => \$data) {
            // Skip expired entries
            if (\$this->isExpired(\$key)) {
                continue;
            }
            
            \$result[\$key] = \$data['value'];
        }
        
        return \$result;
    }
    
    /**
     * Get the size of the memory storage
     *
     * @return int
     */
    public function size(): int
    {
        return count(\$this->storage);
    }
    
    /**
     * Get memory entries in chronological order
     *
     * @param int \$limit Maximum number of entries to return
     * @param int \$offset Offset to start from
     * @return array<mixed>
     */
    public function getHistory(int \$limit = 10, int \$offset = 0): array
    {
        // Sort entries by timestamp
        \$entries = \$this->storage;
        uasort(\$entries, function (\$a, \$b) {
            return \$b['timestamp'] <=> \$a['timestamp']; // Descending order
        });
        
        // Apply offset and limit
        \$entries = array_slice(\$entries, \$offset, \$limit, true);
        
        \$result = [];
        foreach (\$entries as \$key => \$data) {
            // Skip expired entries
            if (\$this->isExpired(\$key)) {
                continue;
            }
            
            \$result[\$key] = [
                'value' => \$data['value'],
                'metadata' => \$data['metadata'],
                'timestamp' => \$data['timestamp'],
            ];
        }
        
        return \$result;
    }
    
    /**
     * Get metadata for a memory entry
     *
     * @param string \$key The key to get metadata for
     * @return array<string, mixed>|null The metadata or null if not found
     */
    public function getMetadata(string \$key): ?array
    {
        if (!isset(\$this->storage[\$key])) {
            return null;
        }
        
        // Check if the entry has expired
        if (\$this->isExpired(\$key)) {
            \$this->delete(\$key);
            return null;
        }
        
        return \$this->storage[\$key]['metadata'];
    }
    
    /**
     * Get the timestamp for a memory entry
     *
     * @param string \$key The key to get the timestamp for
     * @return DateTimeImmutable|null The timestamp or null if not found
     */
    public function getTimestamp(string \$key): ?DateTimeImmutable
    {
        if (!isset(\$this->storage[\$key])) {
            return null;
        }
        
        // Check if the entry has expired
        if (\$this->isExpired(\$key)) {
            \$this->delete(\$key);
            return null;
        }
        
        return new DateTimeImmutable(\$this->storage[\$key]['timestamp']);
    }
    
    /**
     * Check if a memory entry has expired
     *
     * @param string \$key The key to check
     * @return bool Whether the entry has expired
     */
    private function isExpired(string \$key): bool
    {
        if (\$this->ttl === null || !\$this->ttl) {
            return false;
        }
        
        if (!isset(\$this->storage[\$key])) {
            return true;
        }
        
        \$timestamp = new DateTimeImmutable(\$this->storage[\$key]['timestamp']);
        \$expirationTime = \$timestamp->modify("+{\$this->ttl} seconds");
        
        return \$expirationTime < new DateTimeImmutable();
    }
    
    /**
     * Convert a value to a string for searching
     *
     * @param mixed \$value The value to convert
     * @return string The string representation
     */
    private function valueToString(mixed \$value): string
    {
        if (is_string(\$value)) {
            return \$value;
        }
        
        if (is_scalar(\$value)) {
            return (string) \$value;
        }
        
        if (is_array(\$value)) {
            return implode(' ', array_map([\$this, 'valueToString'], \$value));
        }
        
        if (is_object(\$value) && method_exists(\$value, '__toString')) {
            return (string) \$value;
        }
        
        return '';
    }
}
PHP;
    }

    /**
     * Generate additional code for persistent memory providers
     */
    private function generatePersistentMemoryCode(): string
    {
        return <<<'PHP'

    
    /**
     * @var bool Whether the memory provider has been initialized
     */
    private bool $initialized = false;
    
    /**
     * @var string The path to the storage file or connection string
     */
    private string $storagePath;
PHP;
    }
}
