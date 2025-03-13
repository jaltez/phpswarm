<?php

declare(strict_types=1);

namespace PhpSwarm\Async;

use PhpSwarm\Contract\Async\AsyncOperationInterface;
use PhpSwarm\Exception\Async\AsyncOperationException;

/**
 * Base implementation for asynchronous operations.
 */
class AsyncOperation implements AsyncOperationInterface
{
    /**
     * @var string Operation ID
     */
    protected string $id;

    /**
     * @var string Status: 'pending', 'running', 'complete', 'error', 'cancelled'
     */
    protected string $status = 'pending';

    /**
     * @var callable The operation to execute
     */
    protected $operation;

    /**
     * @var mixed The operation result
     */
    protected mixed $result = null;

    /**
     * @var \Throwable|null Error that occurred during execution
     */
    protected ?\Throwable $error = null;

    /**
     * @var resource|null Process handle
     */
    protected $processHandle;

    /**
     * Create a new AsyncOperation instance.
     *
     * @param callable $operation The operation to execute
     */
    public function __construct(callable $operation)
    {
        $this->id = uniqid('async_', true);
        $this->operation = $operation;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function start(): string
    {
        if ($this->status !== 'pending') {
            throw new AsyncOperationException('Operation already started');
        }

        $this->status = 'running';

        // Fork a process to execute the operation
        $this->executeAsync();

        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isComplete(): bool
    {
        if (in_array($this->status, ['complete', 'error', 'cancelled'], true)) {
            return true;
        }

        if ($this->status === 'running') {
            $this->checkProcessStatus();
        }

        return in_array($this->status, ['complete', 'error', 'cancelled'], true);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function wait(?int $timeout = null): mixed
    {
        if ($this->status === 'pending') {
            $this->start();
        }

        $startTime = time();

        while (!$this->isComplete()) {
            if ($timeout !== null && (time() - $startTime) >= $timeout) {
                throw new AsyncOperationException('Operation timed out');
            }

            // Sleep for a short time to avoid CPU spinning
            usleep(10000); // 10ms
        }

        if ($this->error instanceof \Throwable) {
            throw new AsyncOperationException(
                'Operation failed: ' . $this->error->getMessage(),
                0,
                $this->error
            );
        }

        return $this->result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function cancel(): bool
    {
        if (in_array($this->status, ['complete', 'error', 'cancelled'], true)) {
            return false;
        }

        if ($this->status === 'running' && $this->processHandle) {
            // Attempt to kill the process
            $this->terminateProcess();
        }

        $this->status = 'cancelled';
        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getStatus(): string
    {
        if ($this->status === 'running') {
            $this->checkProcessStatus();
        }

        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    /**
     * Execute the operation asynchronously.
     */
    protected function executeAsync(): void
    {
        // This is a simplified implementation that works on a single machine
        // In a production environment, this would use a job queue system

        // Create temporary files for communication
        $resultFile = sys_get_temp_dir() . '/' . $this->id . '.result';
        $errorFile = sys_get_temp_dir() . '/' . $this->id . '.error';
        $pidFile = sys_get_temp_dir() . '/' . $this->id . '.pid';

        // Create a command that will execute the operation
        $command = $this->createAsyncCommand($resultFile, $errorFile, $pidFile);

        // Execute the command
        $this->processHandle = popen($command, 'r');

        if (!$this->processHandle) {
            $this->status = 'error';
            $this->error = new AsyncOperationException('Failed to start async process');
        }
    }

    /**
     * Create a command to execute the operation asynchronously.
     *
     * @param string $resultFile Path to store the operation result
     * @param string $errorFile Path to store any errors
     * @param string $pidFile Path to store the process ID
     * @return string The command to execute
     */
    protected function createAsyncCommand(string $resultFile, string $errorFile, string $pidFile): string
    {
        // Serialize the operation
        $serializedOperation = serialize($this->operation);

        // Create a PHP script that will execute the operation
        $scriptContent = <<<PHP
        <?php
        // Store the PID
        file_put_contents('{$pidFile}', getmypid());
        
        try {
            // Unserialize and execute the operation
            \$operation = unserialize('{$serializedOperation}');
            \$result = \$operation();
            
            // Store the result
            file_put_contents('{$resultFile}', serialize(\$result));
        } catch (\Throwable \$e) {
            // Store the error
            file_put_contents('{$errorFile}', serialize(\$e));
        }
        PHP;

        // Create a temporary script file
        $scriptFile = sys_get_temp_dir() . '/' . $this->id . '.php';
        file_put_contents($scriptFile, $scriptContent);

        // Create a command that will execute the script in the background
        return sprintf(
            'php %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($scriptFile)
        );
    }

    /**
     * Check the status of the process.
     */
    protected function checkProcessStatus(): void
    {
        if ($this->status !== 'running') {
            return;
        }

        $resultFile = sys_get_temp_dir() . '/' . $this->id . '.result';
        $errorFile = sys_get_temp_dir() . '/' . $this->id . '.error';

        // Check if the operation completed
        if (file_exists($resultFile)) {
            $this->result = unserialize(file_get_contents($resultFile));
            $this->status = 'complete';
            $this->cleanupFiles();
        } elseif (file_exists($errorFile)) {
            $this->error = unserialize(file_get_contents($errorFile));
            $this->status = 'error';
            $this->cleanupFiles();
        }
    }

    /**
     * Terminate the process.
     */
    protected function terminateProcess(): void
    {
        $pidFile = sys_get_temp_dir() . '/' . $this->id . '.pid';

        if (file_exists($pidFile)) {
            $pid = (int) file_get_contents($pidFile);

            if ($pid > 0) {
                // Send SIGTERM to the process
                posix_kill($pid, 15);
            }
        }

        if ($this->processHandle) {
            pclose($this->processHandle);
            $this->processHandle = null;
        }

        $this->cleanupFiles();
    }

    /**
     * Clean up temporary files.
     */
    protected function cleanupFiles(): void
    {
        // Clean up temporary files
        $files = [
            sys_get_temp_dir() . '/' . $this->id . '.php',
            sys_get_temp_dir() . '/' . $this->id . '.result',
            sys_get_temp_dir() . '/' . $this->id . '.error',
            sys_get_temp_dir() . '/' . $this->id . '.pid',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        if ($this->processHandle) {
            pclose($this->processHandle);
            $this->processHandle = null;
        }
    }

    /**
     * Destructor to clean up resources.
     */
    public function __destruct()
    {
        if ($this->status === 'running') {
            $this->terminateProcess();
        }

        $this->cleanupFiles();
    }
}
