<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Utility;

/**
 * Interface for event dispatchers.
 *
 * Event dispatchers are responsible for managing event listeners
 * and dispatching events to appropriate listeners.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * @param EventInterface $event The event to dispatch
     * @return EventInterface The event (possibly modified by listeners)
     */
    public function dispatch(EventInterface $event): EventInterface;

    /**
     * Add an event listener.
     *
     * @param string $eventName The name of the event to listen for
     * @param EventListenerInterface|callable $listener The listener
     * @param int $priority The priority (higher = called first)
     */
    public function addListener(string $eventName, EventListenerInterface|callable $listener, int $priority = 0): void;

    /**
     * Remove an event listener.
     *
     * @param string $eventName The name of the event
     * @param EventListenerInterface|callable $listener The listener to remove
     */
    public function removeListener(string $eventName, EventListenerInterface|callable $listener): void;

    /**
     * Add an event subscriber (object that defines its own event subscriptions).
     *
     * @param EventSubscriberInterface $subscriber The subscriber
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void;

    /**
     * Remove an event subscriber.
     *
     * @param EventSubscriberInterface $subscriber The subscriber to remove
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber): void;

    /**
     * Get all listeners for a specific event.
     *
     * @param string $eventName The event name
     * @return array<EventListenerInterface|callable>
     */
    public function getListeners(string $eventName): array;

    /**
     * Check if there are any listeners for a specific event.
     *
     * @param string $eventName The event name
     */
    public function hasListeners(string $eventName): bool;

    /**
     * Remove all listeners for a specific event.
     *
     * @param string $eventName The event name
     */
    public function removeAllListeners(string $eventName): void;

    /**
     * Clear all listeners from the dispatcher.
     */
    public function clearListeners(): void;
}
