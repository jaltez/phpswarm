<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;
use PhpSwarm\Memory\SqliteMemory;

echo "SQLite Memory Example\n";
echo "=====================\n\n";

// Initialize Configuration
$config = PhpSwarmConfig::getInstance();

// Create a SQLite memory instance using the factory
echo "Creating SQLite memory using factory...\n";
$factory = new PhpSwarmFactory($config);

// Use in-memory SQLite database for this example
$sqliteMemory = $factory->createMemory([
    'provider' => 'sqlite',
    'db_path' => ':memory:', // In-memory database
    'ttl' => 3600, // 1 hour TTL
]);

// Alternative: Create a SQLite memory instance directly with a file-based database
// $sqliteMemory = new SqliteMemory([
//     'db_path' => 'storage/example.sqlite',
//     'table_name' => 'memory',
//     'ttl' => 3600,
// ]);

echo "SQLite memory created successfully.\n\n";

// Clear any existing data from previous runs (not needed for in-memory database)
echo "Preparing memory store...\n";
$sqliteMemory->clear();

// Store some data
echo "Adding data to memory...\n";
$sqliteMemory->add('greeting', 'Hello, SQLite!', ['type' => 'greeting', 'language' => 'english']);
$sqliteMemory->add('counter', 42, ['type' => 'number']);
$sqliteMemory->add('user', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'roles' => ['user', 'admin'],
], ['type' => 'user_profile']);

echo "Data added. Memory size: " . $sqliteMemory->size() . " items\n\n";

// Retrieve data
echo "Retrieving data...\n";
echo "Greeting: " . $sqliteMemory->get('greeting') . "\n";
echo "Counter: " . $sqliteMemory->get('counter') . "\n";
$user = $sqliteMemory->get('user');
echo "User: " . $user['name'] . " (" . $user['email'] . ")\n";
echo "User roles: " . implode(', ', $user['roles']) . "\n\n";

// Check metadata
echo "Checking metadata...\n";
$metadata = $sqliteMemory->getMetadata('greeting');
echo "Greeting metadata: " . json_encode($metadata) . "\n";

// Display history (most recent first)
echo "\nMemory history (newest first):\n";
$history = $sqliteMemory->getHistory(10);
foreach ($history as $key => $data) {
    $timestamp = $data['timestamp']->format('Y-m-d H:i:s');
    $valuePreview = is_array($data['value']) ? json_encode($data['value']) : $data['value'];
    echo "- $key ($timestamp): $valuePreview\n";
}

// Test TTL expiration (for demonstration purposes)
echo "\nTesting TTL expiration...\n";
echo "Adding temporary item with 2 second TTL...\n";

// Create a new memory instance with a 2-second TTL for testing expiration
$tempMemory = new SqliteMemory([
    'db_path' => ':memory:',
    'ttl' => 2, // 2 seconds TTL
]);

$tempMemory->add('temp', 'This will expire soon', ['type' => 'temporary']);
echo "Temporary item added.\n";
echo "Item exists immediately after adding: " . ($tempMemory->has('temp') ? 'Yes' : 'No') . "\n";

echo "Waiting for 3 seconds...\n";
sleep(3);

echo "Item exists after waiting: " . ($tempMemory->has('temp') ? 'Yes' : 'No') . "\n\n";

// Search for specific content
echo "Searching for 'john'...\n";
$results = $sqliteMemory->search('john');
foreach ($results as $key => $value) {
    $valuePreview = is_array($value) ? json_encode($value) : $value;
    echo "- $key: $valuePreview\n";
}

// Delete an item
echo "\nDeleting 'counter'...\n";
$sqliteMemory->delete('counter');
echo "Memory size after deletion: " . $sqliteMemory->size() . " items\n";
echo "Counter still exists? " . ($sqliteMemory->has('counter') ? 'Yes' : 'No') . "\n\n";

// Display all data
echo "All remaining memory data:\n";
$allData = $sqliteMemory->all();
foreach ($allData as $key => $value) {
    $valuePreview = is_array($value) ? json_encode($value) : $value;
    echo "- $key: $valuePreview\n";
}

// Clean up
echo "\nCleaning up...\n";
$sqliteMemory->clear();
echo "Memory cleared. Final size: " . $sqliteMemory->size() . " items\n";
echo "Example completed successfully.\n";

// Example of creating a persistent SQLite memory
echo "\nCreating a persistent SQLite memory file...\n";
$storagePath = __DIR__ . '/../storage';
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0755, true);
    echo "Created storage directory: $storagePath\n";
}

$persistentDbPath = $storagePath . '/memory.sqlite';
echo "Database path: $persistentDbPath\n";

$persistentMemory = new SqliteMemory([
    'db_path' => $persistentDbPath,
    'table_name' => 'persistent_memory',
    'ttl' => 86400, // 24 hours TTL
]);

$persistentMemory->add('persistent_key', 'This value will be stored on disk', [
    'created_at' => date('Y-m-d H:i:s'),
    'description' => 'A persistent memory example',
]);

echo "Added persistent memory entry.\n";
echo "To verify persistence, run this example again and check if the entry still exists.\n";

if (file_exists($persistentDbPath)) {
    echo "SQLite database file created at: $persistentDbPath\n";
    echo "File size: " . round(filesize($persistentDbPath) / 1024, 2) . " KB\n";
}

// Check if we have any persistent data from previous runs
$previousEntries = $persistentMemory->all();
if (count($previousEntries) > 1) { // More than the one we just added
    echo "\nFound persistent data from previous runs:\n";
    foreach ($previousEntries as $key => $value) {
        if ($key !== 'persistent_key') { // Skip the one we just added
            $valuePreview = is_array($value) ? json_encode($value) : $value;
            echo "- $key: $valuePreview\n";
        }
    }
} 