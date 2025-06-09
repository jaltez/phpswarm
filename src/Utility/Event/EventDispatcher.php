<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Event;

use PhpSwarm\Contract\Utility\EventDispatcherInterface;
use PhpSwarm\Contract\Utility\EventInterface;
use PhpSwarm\Contract\Utility\EventListenerInterface;
use PhpSwarm\Contract\Utility\EventSubscriberInterface;

/**
 * Default implementation of an event dispatcher.
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<array{listener: EventListenerInterface|callable, priority: int}>>
     */
    private array $listeners = [];

    /**
     * @var array<string, array<EventListenerInterface|callable>>
     */
    private array $sortedListeners = [];

    /**
     * {@inheritdoc}
     */
    public function dispatch(EventInterface $event): EventInterface
    {
        $eventName = $event->getName();
        $listeners = $this->getListeners($eventName);

        foreach ($listeners as $listener) {
            if ($event->isStoppable() && $event->isPropagationStopped()) {
                break;
            }

            if ($listener instanceof EventListenerInterface) {
                if ($listener->canHandle($event)) {
                    $listener->handle($event);
                }
            } else {
                // Callable listener
                $listener($event);
            }
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public function addListener(string $eventName, EventListenerInterface|callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];

        // Reset sorted listeners cache
        unset($this->sortedListeners[$eventName]);
    }

    /**
     * {@inheritdoc}
     */
    public function removeListener(string $eventName, EventListenerInterface|callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $index => $listenerData) {
            if ($listenerData['listener'] === $listener) {
                unset($this->listeners[$eventName][$index]);
                unset($this->sortedListeners[$eventName]);
                break;
            }
        }

        // Clean up empty arrays
        if (empty($this->listeners[$eventName])) {
            unset($this->listeners[$eventName]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $subscriptions = $subscriber->getSubscribedEvents();

        foreach ($subscriptions as $eventName => $params) {
            if (is_string($params)) {
                // Simple method name
                $this->addListener($eventName, [$subscriber, $params]);
            } elseif (is_array($params)) {
                if (isset($params[0]) && is_string($params[0])) {
                    // Single method with optional priority
                    $method = $params[0];
                    $priority = $params[1] ?? 0;
                    $this->addListener($eventName, [$subscriber, $method], $priority);
                } else {
                    // Multiple methods
                    foreach ($params as $methodData) {
                        if (is_string($methodData)) {
                            $this->addListener($eventName, [$subscriber, $methodData]);
                        } elseif (is_array($methodData) && isset($methodData[0])) {
                            $method = $methodData[0];
                            $priority = $methodData[1] ?? 0;
                            $this->addListener($eventName, [$subscriber, $method], $priority);
                        }
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $subscriptions = $subscriber->getSubscribedEvents();

        foreach ($subscriptions as $eventName => $params) {
            if (is_string($params)) {
                // Simple method name
                $this->removeListener($eventName, [$subscriber, $params]);
            } elseif (is_array($params)) {
                if (isset($params[0]) && is_string($params[0])) {
                    // Single method
                    $method = $params[0];
                    $this->removeListener($eventName, [$subscriber, $method]);
                } else {
                    // Multiple methods
                    foreach ($params as $methodData) {
                        if (is_string($methodData)) {
                            $this->removeListener($eventName, [$subscriber, $methodData]);
                        } elseif (is_array($methodData) && isset($methodData[0])) {
                            $method = $methodData[0];
                            $this->removeListener($eventName, [$subscriber, $method]);
                        }
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners(string $eventName): array
    {
        if (isset($this->sortedListeners[$eventName])) {
            return $this->sortedListeners[$eventName];
        }

        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        // Sort listeners by priority (highest first)
        $listeners = $this->listeners[$eventName];
        usort($listeners, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        // Extract just the listeners
        $sortedListeners = array_map(function ($item) {
            return $item['listener'];
        }, $listeners);

        // Cache the sorted result
        $this->sortedListeners[$eventName] = $sortedListeners;

        return $sortedListeners;
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) && !empty($this->listeners[$eventName]);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllListeners(string $eventName): void
    {
        unset($this->listeners[$eventName], $this->sortedListeners[$eventName]);
    }

    /**
     * {@inheritdoc}
     */
    public function clearListeners(): void
    {
        $this->listeners = [];
        $this->sortedListeners = [];
    }
}
