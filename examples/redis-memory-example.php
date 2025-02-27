<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;
use PhpSwarm\Memory\RedisMemory;

// Check if we have Redis server available
echo "Checking Redis server connectivity...\n";
try {
    $redis = new \Redis();
    $connected = $redis->connect(
        getenv('REDIS_HOST') ?: 'localhost',
        (int) (getenv('REDIS_PORT') ?: 6379),
        1.0 // 1 second timeout
    );
    
    if (!$connected) {
        echo "Error: Could not connect to Redis server. Ensure Redis is running and accessible.\n";
        echo "You can set REDIS_HOST and REDIS_PORT environment variables to customize the connection.\n";
        exit(1);
    }
    
    $redis->ping();
    echo "Successfully connected to Redis server.\n\n";
    $redis->close();
} catch (\Exception $e) {
    echo "Error: Redis connection failed: " . $e->getMessage() . "\n";
    echo "This example requires a Redis server. Please ensure Redis is installed and running.\n";
    exit(1);
}

// Initialize Configuration
$config = PhpSwarmConfig::getInstance();

// Create a Redis memory instance using the factory
echo "Creating Redis memory using factory...\n";
$factory = new PhpSwarmFactory($config);
$redisMemory = $factory->createMemory([
    'provider' => 'redis',
    'prefix' => 'example:',
    'ttl' => 3600, // 1 hour TTL
]);

// Alternative: Create a Redis memory instance directly
// $redisMemory = new RedisMemory([
//     'host' => getenv('REDIS_HOST') ?: 'localhost',
//     'port' => (int) (getenv('REDIS_PORT') ?: 6379),
//     'database' => 0,
//     'prefix' => 'example:',
//     'ttl' => 3600,
// ]);

echo "Redis memory created successfully.\n\n";

// Clear any existing data from previous runs
echo "Clearing any existing memory data...\n";
$redisMemory->clear();
echo "Memory cleared.\n\n";

// Store some data
echo "Adding data to memory...\n";
$redisMemory->add('greeting', 'Hello, Redis!', ['type' => 'greeting', 'language' => 'english']);
$redisMemory->add('counter', 42, ['type' => 'number']);
$redisMemory->add('user', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
], ['type' => 'user_profile']);

echo "Data added. Memory size: " . $redisMemory->size() . " items\n\n";

// Retrieve data
echo "Retrieving data...\n";
echo "Greeting: " . $redisMemory->get('greeting') . "\n";
echo "Counter: " . $redisMemory->get('counter') . "\n";
$user = $redisMemory->get('user');
echo "User: " . $user['name'] . " (" . $user['email'] . ")\n\n";

// Check metadata
echo "Checking metadata...\n";
$metadata = $redisMemory->getMetadata('greeting');
echo "Greeting metadata: " . json_encode($metadata) . "\n";

// Display history (most recent first)
echo "\nMemory history (newest first):\n";
$history = $redisMemory->getHistory(10);
foreach ($history as $key => $data) {
    $timestamp = $data['timestamp']->format('Y-m-d H:i:s');
    $valuePreview = is_array($data['value']) ? json_encode($data['value']) : $data['value'];
    echo "- $key ($timestamp): $valuePreview\n";
}

// Search for specific content
echo "\nSearching for 'john'...\n";
$results = $redisMemory->search('john');
foreach ($results as $key => $value) {
    $valuePreview = is_array($value) ? json_encode($value) : $value;
    echo "- $key: $valuePreview\n";
}

// Delete an item
echo "\nDeleting 'counter'...\n";
$redisMemory->delete('counter');
echo "Memory size after deletion: " . $redisMemory->size() . " items\n";
echo "Counter still exists? " . ($redisMemory->has('counter') ? 'Yes' : 'No') . "\n\n";

// Display all data
echo "All remaining memory data:\n";
$allData = $redisMemory->all();
foreach ($allData as $key => $value) {
    $valuePreview = is_array($value) ? json_encode($value) : $value;
    echo "- $key: $valuePreview\n";
}

// Clean up
echo "\nCleaning up...\n";
$redisMemory->clear();
echo "Memory cleared. Final size: " . $redisMemory->size() . " items\n";
echo "Example completed successfully.\n"; 