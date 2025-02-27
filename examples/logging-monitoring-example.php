<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;

// Initialize Configuration
$config = PhpSwarmConfig::getInstance();

// Create the factory
$factory = new PhpSwarmFactory($config);

echo "Logging and Performance Monitoring Example\n";
echo "=========================================\n\n";

// Create a logger
echo "Creating logger...\n";
$logger = $factory->createLogger([
    'log_file' => __DIR__ . '/../logs/example.log',
    'min_level' => 'debug',
    'include_timestamps' => true,
]);

// Create a performance monitor
echo "Creating performance monitor...\n";
$monitor = $factory->createMonitor([
    'logger' => $logger,
]);

// Log some messages at different levels
$logger->info("Starting example script", [
    'script' => 'logging-monitoring-example.php',
    'time' => date('Y-m-d H:i:s'),
]);

$logger->debug("Debugging information", [
    'memory_usage' => memory_get_usage(true),
]);

// Start tracking a process
$processId = $monitor->beginProcess('example_process', [
    'description' => 'Example process for demonstration',
]);

$logger->info("Started process", [
    'process_id' => $processId,
]);

// Simulate some work with timer
$timerId = $monitor->startTimer('calculation', [
    'type' => 'demo',
]);

// Demo of counter metrics
echo "\nIncrementing counters...\n";
$monitor->incrementCounter('demo_counter');
$monitor->incrementCounter('demo_counter');
$monitor->incrementCounter('items_processed', 5);

echo "Counter 'demo_counter' value: " . $monitor->getMetric('demo_counter') . "\n";
echo "Counter 'items_processed' value: " . $monitor->getMetric('items_processed') . "\n";

// Simulate work
echo "\nSimulating work...\n";
sleep(2);

// Stop the timer
$elapsed = $monitor->stopTimer($timerId);
echo "Work completed in {$elapsed} seconds\n";

// Record custom metrics
$monitor->recordMetric('example_metric', 42, [
    'description' => 'Example metric value',
]);

$monitor->recordMetric('temperature', 98.6, [
    'unit' => 'F',
]);

// Record multiple operations using timer
echo "\nRunning multiple timed operations...\n";

$operations = [
    'operation1' => 1,
    'operation2' => 2,
    'operation3' => 0.5,
];

foreach ($operations as $name => $seconds) {
    // Start a timer for this operation
    $opTimerId = $monitor->startTimer($name);
    
    // Log start
    $logger->debug("Starting operation", [
        'operation' => $name,
        'expected_duration' => $seconds,
    ]);
    
    // Simulate work
    echo "  - Running $name for $seconds seconds...\n";
    usleep($seconds * 1000000);
    
    // Stop timer and get elapsed time
    $opElapsed = $monitor->stopTimer($opTimerId);
    
    // Log completion
    $logger->debug("Completed operation", [
        'operation' => $name,
        'duration' => $opElapsed,
    ]);
}

// Intentionally log a warning
$logger->warning("This is a warning message", [
    'alert_level' => 'medium',
    'action' => 'notification',
]);

// End the process
$monitor->endProcess($processId, [
    'result' => 'success',
    'items_processed' => $monitor->getMetric('items_processed'),
]);

// Demonstrate error handling with try/catch
echo "\nDemonstrating error handling...\n";

try {
    // Start a new process
    $errorProcessId = $monitor->beginProcess('error_demo');
    
    // Start a timer
    $errorTimerId = $monitor->startTimer('error_operation');
    
    $logger->info("Attempting risky operation");
    
    // Simulate an error
    echo "  - Simulating an error...\n";
    throw new \RuntimeException("This is a simulated error");
    
    // This code is never reached
    $monitor->stopTimer($errorTimerId);
    $monitor->endProcess($errorProcessId);
    
} catch (\Throwable $e) {
    // Log the error
    $logger->error("An error occurred", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    // Record the failure in the monitor
    if (isset($errorTimerId)) {
        $monitor->stopTimer($errorTimerId, [
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
    
    if (isset($errorProcessId)) {
        $monitor->failProcess($errorProcessId, $e->getMessage(), [
            'exception_type' => get_class($e),
        ]);
    }
    
    echo "  - Error handled: " . $e->getMessage() . "\n";
}

// Display metrics
echo "\nPerformance Metrics:\n";
echo "-------------------\n";
$metrics = $monitor->getMetrics();

foreach ($metrics as $key => $value) {
    if (is_scalar($value)) {
        echo "  - $key: ";
        if (is_float($value)) {
            echo number_format($value, 4);
        } else {
            echo $value;
        }
        echo "\n";
    }
}

// Show process information
echo "\nProcesses:\n";
echo "---------\n";
$processes = $monitor->getCompletedProcesses();
foreach ($processes as $id => $process) {
    echo "  - Process: {$process['name']}\n";
    echo "    Status: {$process['status']}\n";
    echo "    Duration: " . number_format($process['duration'], 4) . " seconds\n";
    echo "\n";
}

$logger->info("Example script completed", [
    'metrics_count' => count($metrics),
    'processes_count' => count($processes),
]);

echo "\nExample completed. Check the log file at: " . __DIR__ . "/../logs/example.log\n"; 