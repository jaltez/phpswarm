<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;
use PhpSwarm\Tool\FileSystem\FileSystemTool;

echo "FileSystem Tool Example\n";
echo "=====================\n\n";

// Initialize Configuration
$config = PhpSwarmConfig::getInstance();

// Create the factory
$factory = new PhpSwarmFactory($config);

// Create a FileSystemTool using the factory
echo "Creating FileSystem tool using factory...\n";
$fileSystemTool = $factory->createTool('file_system', [
    'base_directory' => __DIR__ . '/../storage/example', // Restrict operations to this directory
]);

// Alternatively, create the tool directly
// $fileSystemTool = new FileSystemTool([
//     'base_directory' => __DIR__ . '/../storage/example',
// ]);

echo "FileSystem tool created successfully.\n\n";

// Create a test directory
echo "Creating directories...\n";
$fileSystemTool->run([
    'operation' => 'create_directory', 
    'path' => 'test-dir',
    'recursive' => true,
]);

$fileSystemTool->run([
    'operation' => 'create_directory', 
    'path' => 'test-dir/nested',
    'recursive' => true,
]);

echo "Directories created successfully.\n\n";

// Write files
echo "Writing files...\n";
$fileSystemTool->run([
    'operation' => 'write', 
    'path' => 'test-dir/test-file.txt',
    'content' => "Hello, World!\nThis is a test file created by the FileSystemTool.",
]);

$fileSystemTool->run([
    'operation' => 'write', 
    'path' => 'test-dir/nested/nested-file.txt',
    'content' => "This is a nested file.",
]);

echo "Files written successfully.\n\n";

// Check if a file exists
echo "Checking if files exist...\n";
$fileExists = $fileSystemTool->run([
    'operation' => 'exists', 
    'path' => 'test-dir/test-file.txt',
]);

echo "File 'test-dir/test-file.txt' exists: " . ($fileExists ? 'Yes' : 'No') . "\n";

$nonExistentFileExists = $fileSystemTool->run([
    'operation' => 'exists', 
    'path' => 'non-existent-file.txt',
]);

echo "File 'non-existent-file.txt' exists: " . ($nonExistentFileExists ? 'Yes' : 'No') . "\n\n";

// Read a file
echo "Reading file content...\n";
$content = $fileSystemTool->run([
    'operation' => 'read', 
    'path' => 'test-dir/test-file.txt',
]);

echo "Content of 'test-dir/test-file.txt':\n{$content}\n\n";

// Append to a file
echo "Appending to file...\n";
$fileSystemTool->run([
    'operation' => 'append', 
    'path' => 'test-dir/test-file.txt',
    'content' => "\n\nThis content was appended.",
]);

echo "Content appended successfully.\n";

// Read the file again to confirm the append
$updatedContent = $fileSystemTool->run([
    'operation' => 'read', 
    'path' => 'test-dir/test-file.txt',
]);

echo "Updated content of 'test-dir/test-file.txt':\n{$updatedContent}\n\n";

// List directory contents
echo "Listing directory contents...\n";
$directoryContents = $fileSystemTool->run([
    'operation' => 'list_directory', 
    'path' => 'test-dir',
]);

echo "Contents of 'test-dir':\n";
foreach ($directoryContents as $name => $info) {
    $type = $info['type'];
    $size = $info['size'] ?? 'N/A';
    $modified = date('Y-m-d H:i:s', $info['modified']);
    
    echo "- {$name} ({$type}, {$size} bytes, modified: {$modified})\n";
}
echo "\n";

// List directory contents recursively
echo "Listing directory contents recursively...\n";
$recursiveContents = $fileSystemTool->run([
    'operation' => 'list_directory', 
    'path' => 'test-dir',
    'recursive' => true,
]);

echo "Recursive contents of 'test-dir':\n";
foreach ($recursiveContents as $path => $info) {
    $type = $info['type'];
    $size = $info['size'] ?? 'N/A';
    
    echo "- {$path} ({$type}, {$size} bytes)\n";
}
echo "\n";

// Delete a file
echo "Deleting a file...\n";
$fileSystemTool->run([
    'operation' => 'delete', 
    'path' => 'test-dir/nested/nested-file.txt',
]);

echo "File 'test-dir/nested/nested-file.txt' deleted successfully.\n\n";

// Delete a directory
echo "Deleting directories...\n";
// First try to delete a non-empty directory without recursive flag
try {
    $fileSystemTool->run([
        'operation' => 'delete_directory', 
        'path' => 'test-dir',
    ]);
} catch (Exception $e) {
    echo "Expected error: " . $e->getMessage() . "\n";
}

// Now delete the nested directory
$fileSystemTool->run([
    'operation' => 'delete_directory', 
    'path' => 'test-dir/nested',
]);
echo "Directory 'test-dir/nested' deleted successfully.\n";

// Now delete the main directory with recursive flag
$fileSystemTool->run([
    'operation' => 'delete_directory', 
    'path' => 'test-dir',
    'recursive' => true,
]);
echo "Directory 'test-dir' deleted successfully with recursive flag.\n\n";

echo "FileSystem tool example completed successfully.\n"; 