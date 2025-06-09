<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Utility;

/**
 * Interface for event subscribers.
 *
 * Event subscribers are objects that define their own event subscriptions
 * and can handle multiple events.
 */
interface EventSubscriberInterface
{
    /**
     * Get the event subscriptions for this subscriber.
     *
     * Returns an array where keys are event names and values are either:
     * - Method name (string)
     * - Array with method name and priority: ['method', priority]
     * - Array of method definitions: [['method1', priority1], ['method2', priority2]]
     *
     * @return array<string, string|array<int|string, int|string>>
     */
    public function getSubscribedEvents(): array;
}
