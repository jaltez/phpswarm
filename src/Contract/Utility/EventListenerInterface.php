<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Utility;

/**
 * Interface for event listeners.
 *
 * Event listeners are objects that can handle events when they are dispatched.
 */
interface EventListenerInterface
{
    /**
     * Handle the event.
     *
     * @param EventInterface $event The event to handle
     */
    public function handle(EventInterface $event): void;

    /**
     * Get the priority of this listener.
     * Higher priority listeners are called first.
     */
    public function getPriority(): int;

    /**
     * Check if this listener can handle the given event.
     *
     * @param EventInterface $event The event to check
     */
    public function canHandle(EventInterface $event): bool;

    /**
     * Get the event names this listener is interested in.
     *
     * @return array<string> Array of event names
     */
    public function getSubscribedEvents(): array;
}
