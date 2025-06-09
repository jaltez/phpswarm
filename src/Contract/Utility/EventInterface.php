<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Utility;

use DateTimeInterface;

/**
 * Interface for events in the PHPSwarm event system.
 *
 * Events represent something that has happened in the system
 * and can be listened to by event listeners.
 */
interface EventInterface
{
    /**
     * Get the event name/type.
     */
    public function getName(): string;

    /**
     * Get the event data/payload.
     *
     * @return array<string, mixed>
     */
    public function getData(): array;

    /**
     * Get the timestamp when the event was created.
     */
    public function getTimestamp(): DateTimeInterface;

    /**
     * Get the event source/context.
     */
    public function getSource(): string;

    /**
     * Check if the event is stoppable.
     */
    public function isStoppable(): bool;

    /**
     * Check if event propagation has been stopped.
     */
    public function isPropagationStopped(): bool;

    /**
     * Stop event propagation to other listeners.
     */
    public function stopPropagation(): void;

    /**
     * Get additional metadata about the event.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Add metadata to the event.
     *
     * @param string $key The metadata key
     * @param mixed $value The metadata value
     */
    public function addMetadata(string $key, mixed $value): self;
}
