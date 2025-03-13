<?php

declare(strict_types=1);

namespace PhpSwarm\Tool\FileSystem;

use PhpSwarm\Exception\Tool\ToolExecutionException;
use PhpSwarm\Tool\BaseTool;

/**
 * Tool for interacting with the file system.
 *
 * This tool allows agents to read, write, and manipulate files and directories.
 */
class FileSystemTool extends BaseTool
{
    /**
     * @var string|null The base directory to restrict file operations
     */
    private ?string $baseDirectory;

    /**
     * @var array<string> List of allowed operations
     */
    private readonly array $allowedOperations;

    /**
     * Create a new FileSystemTool instance.
     *
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(array $config = [])
    {
        parent::__construct(
            'file_system',
            'Interact with the file system to read, write, and manage files and directories'
        );

        // Set the base directory to restrict file operations (for security)
        $this->baseDirectory = $config['base_directory'] ?? null;
        if ($this->baseDirectory) {
            $this->baseDirectory = rtrim((string) (realpath($this->baseDirectory) ?: $this->baseDirectory), '/\\');
        }

        // Set allowed operations (default: all operations are allowed)
        $this->allowedOperations = $config['allowed_operations'] ?? [
            'read', 'write', 'append', 'delete', 'exists',
            'list_directory', 'create_directory', 'delete_directory'
        ];

        // Define parameters schema
        $this->parametersSchema = [
            'operation' => [
                'type' => 'string',
                'enum' => $this->allowedOperations,
                'description' => 'The file operation to perform',
                'required' => true,
            ],
            'path' => [
                'type' => 'string',
                'description' => 'Path to the file or directory',
                'required' => true,
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Content to write to the file (for write/append operations)',
                'required' => false,
            ],
            'recursive' => [
                'type' => 'boolean',
                'description' => 'Whether to perform the operation recursively (for directory operations)',
                'required' => false,
                'default' => false,
            ],
        ];

        // Add tag
        $this->addTag('file-system');
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function run(array $parameters = []): mixed
    {
        $this->validateParameters($parameters);

        $operation = $parameters['operation'];
        $path = $parameters['path'];

        // Ensure the operation is allowed
        if (!in_array($operation, $this->allowedOperations, true)) {
            throw new ToolExecutionException("Operation '{$operation}' is not allowed");
        }

        // Resolve full path and check if it's within base directory (if set)
        $fullPath = $this->resolvePath($path);

        return match ($operation) {
            'read' => $this->readFile($fullPath),
            'write' => $this->writeFile($fullPath, $parameters['content'] ?? ''),
            'append' => $this->appendToFile($fullPath, $parameters['content'] ?? ''),
            'delete' => $this->deleteFile($fullPath),
            'exists' => $this->fileExists($fullPath),
            'list_directory' => $this->listDirectory($fullPath, $parameters['recursive'] ?? false),
            'create_directory' => $this->createDirectory($fullPath, $parameters['recursive'] ?? false),
            'delete_directory' => $this->deleteDirectory($fullPath, $parameters['recursive'] ?? false),
            default => throw new ToolExecutionException("Unsupported operation: {$operation}"),
        };
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isAvailable() : bool
    {
        // Check if the base directory is accessible (if set)
        return !($this->baseDirectory && !file_exists($this->baseDirectory));
    }

    /**
     * Read a file and return its contents.
     *
     * @param string $path Full path to the file
     * @return string File contents
     * @throws ToolExecutionException If the file cannot be read
     */
    private function readFile(string $path): string
    {
        if (!file_exists($path)) {
            throw new ToolExecutionException("File not found: {$path}");
        }

        if (!is_readable($path)) {
            throw new ToolExecutionException("File is not readable: {$path}");
        }

        if (is_dir($path)) {
            throw new ToolExecutionException("Path is a directory, not a file: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new ToolExecutionException("Failed to read file: {$path}");
        }

        return $content;
    }

    /**
     * Write content to a file.
     *
     * @param string $path Full path to the file
     * @param string $content Content to write
     * @return bool True if successful
     * @throws ToolExecutionException If the file cannot be written
     */
    private function writeFile(string $path, string $content): bool
    {
        $directory = dirname($path);

        if (!file_exists($directory) && !mkdir($directory, 0755, true)) {
            throw new ToolExecutionException("Failed to create directory: {$directory}");
        }

        if (file_exists($path) && !is_writable($path)) {
            throw new ToolExecutionException("File is not writable: {$path}");
        }

        if (file_put_contents($path, $content) === false) {
            throw new ToolExecutionException("Failed to write to file: {$path}");
        }

        return true;
    }

    /**
     * Append content to a file.
     *
     * @param string $path Full path to the file
     * @param string $content Content to append
     * @return bool True if successful
     * @throws ToolExecutionException If the file cannot be written
     */
    private function appendToFile(string $path, string $content): bool
    {
        $directory = dirname($path);

        if (!file_exists($directory) && !mkdir($directory, 0755, true)) {
            throw new ToolExecutionException("Failed to create directory: {$directory}");
        }

        if (file_exists($path) && !is_writable($path)) {
            throw new ToolExecutionException("File is not writable: {$path}");
        }

        if (file_put_contents($path, $content, FILE_APPEND) === false) {
            throw new ToolExecutionException("Failed to append to file: {$path}");
        }

        return true;
    }

    /**
     * Delete a file.
     *
     * @param string $path Full path to the file
     * @return bool True if successful
     * @throws ToolExecutionException If the file cannot be deleted
     */
    private function deleteFile(string $path): bool
    {
        if (!file_exists($path)) {
            throw new ToolExecutionException("File not found: {$path}");
        }

        if (is_dir($path)) {
            throw new ToolExecutionException("Path is a directory, not a file: {$path}");
        }

        if (!is_writable($path)) {
            throw new ToolExecutionException("File is not writable: {$path}");
        }

        if (!unlink($path)) {
            throw new ToolExecutionException("Failed to delete file: {$path}");
        }

        return true;
    }

    /**
     * Check if a file or directory exists.
     *
     * @param string $path Full path to the file or directory
     * @return bool True if the file or directory exists
     */
    private function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * List the contents of a directory.
     *
     * @param string $path Full path to the directory
     * @param bool $recursive Whether to list subdirectories recursively
     * @return array<string, mixed> Directory contents
     * @throws ToolExecutionException If the directory cannot be read
     */
    private function listDirectory(string $path, bool $recursive = false): array
    {
        if (!file_exists($path)) {
            throw new ToolExecutionException("Directory not found: {$path}");
        }

        if (!is_dir($path)) {
            throw new ToolExecutionException("Path is not a directory: {$path}");
        }

        if (!is_readable($path)) {
            throw new ToolExecutionException("Directory is not readable: {$path}");
        }

        if ($recursive) {
            return $this->listDirectoryRecursive($path);
        }

        $items = [];
        $entries = scandir($path);

        if ($entries === false) {
            throw new ToolExecutionException("Failed to list directory: {$path}");
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $fullPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $entry;
            $items[$entry] = [
                'type' => is_dir($fullPath) ? 'directory' : 'file',
                'size' => is_file($fullPath) ? filesize($fullPath) : null,
                'modified' => filemtime($fullPath),
            ];
        }

        return $items;
    }

    /**
     * List the contents of a directory recursively.
     *
     * @param string $path Full path to the directory
     * @return array<string, mixed> Directory contents
     */
    private function listDirectoryRecursive(string $path): array
    {
        $items = [];
        $path = rtrim($path, '/\\');
        $basePath = strlen($path) + 1;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relativePath = substr((string) $file->getPathname(), $basePath);
            $items[$relativePath] = [
                'type' => $file->isDir() ? 'directory' : 'file',
                'size' => $file->isFile() ? $file->getSize() : null,
                'modified' => $file->getMTime(),
            ];
        }

        return $items;
    }

    /**
     * Create a directory.
     *
     * @param string $path Full path to the directory
     * @param bool $recursive Whether to create parent directories if they don't exist
     * @return bool True if successful
     * @throws ToolExecutionException If the directory cannot be created
     */
    private function createDirectory(string $path, bool $recursive = false): bool
    {
        if (file_exists($path)) {
            if (is_dir($path)) {
                return true; // Directory already exists
            }
            throw new ToolExecutionException("Path exists but is not a directory: {$path}");
        }

        if (!mkdir($path, 0755, $recursive)) {
            throw new ToolExecutionException("Failed to create directory: {$path}");
        }

        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $path Full path to the directory
     * @param bool $recursive Whether to delete the directory contents recursively
     * @return bool True if successful
     * @throws ToolExecutionException If the directory cannot be deleted
     */
    private function deleteDirectory(string $path, bool $recursive = false): bool
    {
        if (!file_exists($path)) {
            throw new ToolExecutionException("Directory not found: {$path}");
        }

        if (!is_dir($path)) {
            throw new ToolExecutionException("Path is not a directory: {$path}");
        }

        if (!is_writable($path)) {
            throw new ToolExecutionException("Directory is not writable: {$path}");
        }

        if ($recursive) {
            $this->deleteDirectoryRecursive($path);
            return true;
        }

        $entries = scandir($path);
        if ($entries === false) {
            throw new ToolExecutionException("Failed to scan directory: {$path}");
        }

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                throw new ToolExecutionException("Directory is not empty: {$path}");
            }
        }

        if (!rmdir($path)) {
            throw new ToolExecutionException("Failed to delete directory: {$path}");
        }

        return true;
    }

    /**
     * Delete a directory and its contents recursively.
     *
     * @param string $path Full path to the directory
     * @throws ToolExecutionException If a file or directory cannot be deleted
     */
    private function deleteDirectoryRecursive(string $path): void
    {
        $entries = scandir($path);
        if ($entries === false) {
            throw new ToolExecutionException("Failed to scan directory: {$path}");
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $fullPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($fullPath)) {
                $this->deleteDirectoryRecursive($fullPath);
            } elseif (!unlink($fullPath)) {
                throw new ToolExecutionException("Failed to delete file: {$fullPath}");
            }
        }

        if (!rmdir($path)) {
            throw new ToolExecutionException("Failed to delete directory: {$path}");
        }
    }

    /**
     * Resolve and validate the file path to ensure it's within the base directory.
     *
     * @param string $path The path to resolve
     * @return string The full path
     * @throws ToolExecutionException If the path is outside the base directory
     */
    private function resolvePath(string $path): string
    {
        // Convert to absolute path if it's relative
        if (!$this->isAbsolutePath($path) && $this->baseDirectory) {
            $path = $this->baseDirectory . DIRECTORY_SEPARATOR . $path;
        }

        // Resolve real path
        $realPath = realpath($path);

        // If the path doesn't exist yet, check its parent directory instead
        if ($realPath === false) {
            $directory = dirname($path);
            $realDirectory = realpath($directory);

            if ($realDirectory === false) {
                throw new ToolExecutionException("Invalid path: {$path}");
            }

            $filename = basename($path);
            $realPath = $realDirectory . DIRECTORY_SEPARATOR . $filename;
        }

        // Check if the path is within the base directory (if set)
        if ($this->baseDirectory) {
            $realPath = str_replace('\\', '/', $realPath);
            $baseDir = str_replace('\\', '/', $this->baseDirectory);

            if (!str_starts_with($realPath, $baseDir)) {
                throw new ToolExecutionException(
                    "Access denied: Path is outside the allowed base directory"
                );
            }
        }

        return $realPath;
    }

    /**
     * Check if a path is absolute.
     *
     * @param string $path The path to check
     * @return bool True if the path is absolute
     */
    private function isAbsolutePath(string $path): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return (bool) preg_match('/^[A-Z]:\\\\/i', $path) || str_starts_with($path, '\\\\');
        }

        return str_starts_with($path, '/');
    }
}
