<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Async;

/**
 * Interface for asynchronous operations.
 */
interface AsyncOperationInterface
{
    /**
     * Start the asynchronous operation.
     *
     * @return string Operation ID
     */
    public function start(): string;

    /**
     * Check if the operation is complete.
     *
     * @return bool True if the operation is complete, false otherwise
     */
    public function isComplete(): bool;

    /**
     * Wait for the operation to complete and return the result.
     *
     * @param int|null $timeout Maximum time to wait in seconds, null for indefinite
     * @return mixed The operation result
     */
    public function wait(?int $timeout = null): mixed;

    /**
     * Get the result of the operation.
     *
     * @return mixed The operation result or null if not complete
     */
    public function getResult(): mixed;

    /**
     * Cancel the operation.
     *
     * @return bool True if the operation was canceled, false otherwise
     */
    public function cancel(): bool;

    /**
     * Get the operation ID.
     *
     * @return string Operation ID
     */
    public function getId(): string;

    /**
     * Get the operation status.
     *
     * @return string Status: 'pending', 'running', 'complete', 'error', 'cancelled'
     */
    public function getStatus(): string;

    /**
     * Get the error if the operation failed.
     *
     * @return \Throwable|null The error or null if no error occurred
     */
    public function getError(): ?\Throwable;
}
