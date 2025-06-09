<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Event;

use PhpSwarm\Contract\Utility\EventInterface;
use PhpSwarm\Contract\Utility\EventListenerInterface;

/**
 * Base implementation of an event listener.
 */
abstract class BaseEventListener implements EventListenerInterface
{
    /**
     * @var int The priority of this listener
     */
    protected int $priority = 0;

    /**
     * @var array<string> The event names this listener is interested in
     */
    protected array $subscribedEvents = [];

    /**
     * Create a new base event listener.
     *
     * @param int $priority The priority of this listener
     */
    public function __construct(int $priority = 0)
    {
        $this->priority = $priority;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * {@inheritdoc}
     */
    public function canHandle(EventInterface $event): bool
    {
        $subscribedEvents = $this->getSubscribedEvents();
        return empty($subscribedEvents) || in_array($event->getName(), $subscribedEvents, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return $this->subscribedEvents;
    }

    /**
     * Set the subscribed events for this listener.
     *
     * @param array<string> $events The event names
     */
    protected function setSubscribedEvents(array $events): self
    {
        $this->subscribedEvents = $events;
        return $this;
    }

    /**
     * Add an event to the subscribed events.
     *
     * @param string $eventName The event name
     */
    protected function addSubscribedEvent(string $eventName): self
    {
        if (!in_array($eventName, $this->subscribedEvents, true)) {
            $this->subscribedEvents[] = $eventName;
        }
        return $this;
    }
}
