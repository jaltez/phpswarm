<?php

declare(strict_types=1);

namespace PhpSwarm\Async;

use PhpSwarm\Contract\Async\AsyncOperationInterface;
use PhpSwarm\Exception\Async\AsyncOperationException;

/**
 * Manager for handling multiple asynchronous operations.
 */
class AsyncManager
{
    /**
     * @var array<string, AsyncOperationInterface> Active operations
     */
    private array $operations = [];

    /**
     * Create a new asynchronous operation.
     *
     * @param callable $operation The operation to execute
     * @param bool $autoStart Whether to start the operation immediately
     * @return AsyncOperationInterface The created operation
     */
    public function createOperation(callable $operation, bool $autoStart = true): AsyncOperationInterface
    {
        $asyncOp = new AsyncOperation($operation);
        $this->operations[$asyncOp->getId()] = $asyncOp;

        if ($autoStart) {
            $asyncOp->start();
        }

        return $asyncOp;
    }

    /**
     * Get an operation by ID.
     *
     * @param string $id Operation ID
     * @return AsyncOperationInterface|null The operation or null if not found
     */
    public function getOperation(string $id): ?AsyncOperationInterface
    {
        return $this->operations[$id] ?? null;
    }

    /**
     * Get all active operations.
     *
     * @return array<string, AsyncOperationInterface> Active operations
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Remove a completed operation.
     *
     * @param string $id Operation ID
     * @return bool True if the operation was removed, false otherwise
     */
    public function removeOperation(string $id): bool
    {
        if (!isset($this->operations[$id])) {
            return false;
        }

        $operation = $this->operations[$id];

        if (!$operation->isComplete()) {
            return false;
        }

        unset($this->operations[$id]);
        return true;
    }

    /**
     * Cancel an operation.
     *
     * @param string $id Operation ID
     * @return bool True if the operation was canceled, false otherwise
     */
    public function cancelOperation(string $id): bool
    {
        if (!isset($this->operations[$id])) {
            return false;
        }

        $operation = $this->operations[$id];
        $result = $operation->cancel();

        if ($result) {
            unset($this->operations[$id]);
        }

        return $result;
    }

    /**
     * Wait for multiple operations to complete.
     *
     * @param array<string> $ids Operation IDs to wait for
     * @param int|null $timeout Maximum time to wait in seconds, null for indefinite
     * @return array<string, mixed> Operation results keyed by operation ID
     * @throws AsyncOperationException If an operation fails
     */
    public function waitForOperations(array $ids, ?int $timeout = null): array
    {
        $startTime = time();
        $results = [];
        $pendingIds = $ids;

        while ($pendingIds !== []) {
            if ($timeout !== null && (time() - $startTime) >= $timeout) {
                throw new AsyncOperationException('Operations timed out');
            }

            foreach ($pendingIds as $key => $id) {
                if (!isset($this->operations[$id])) {
                    unset($pendingIds[$key]);
                    continue;
                }

                $operation = $this->operations[$id];

                if ($operation->isComplete()) {
                    if ($operation->getError()) {
                        throw new AsyncOperationException(
                            'Operation failed: ' . $operation->getError()->getMessage(),
                            0,
                            $operation->getError()
                        );
                    }

                    $results[$id] = $operation->getResult();
                    unset($pendingIds[$key]);
                }
            }

            if ($pendingIds !== []) {
                // Sleep for a short time to avoid CPU spinning
                usleep(10000); // 10ms
            }
        }

        return $results;
    }

    /**
     * Wait for all operations to complete.
     *
     * @param int|null $timeout Maximum time to wait in seconds, null for indefinite
     * @return array<string, mixed> Operation results keyed by operation ID
     * @throws AsyncOperationException If an operation fails
     */
    public function waitForAll(?int $timeout = null): array
    {
        return $this->waitForOperations(array_keys($this->operations), $timeout);
    }

    /**
     * Cancel all operations.
     *
     * @return int Number of operations canceled
     */
    public function cancelAll(): int
    {
        $count = 0;

        foreach (array_keys($this->operations) as $id) {
            if ($this->cancelOperation($id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clean up completed operations.
     *
     * @return int Number of operations removed
     */
    public function cleanup(): int
    {
        $count = 0;

        foreach (array_keys($this->operations) as $id) {
            $operation = $this->operations[$id];

            if ($operation->isComplete()) {
                unset($this->operations[$id]);
                $count++;
            }
        }

        return $count;
    }
}
