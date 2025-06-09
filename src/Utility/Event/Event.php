<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Event;

use DateTimeInterface;
use DateTime;
use PhpSwarm\Contract\Utility\EventInterface;

/**
 * Base implementation of an event.
 */
class Event implements EventInterface
{
    /**
     * @var bool Whether event propagation has been stopped
     */
    private bool $propagationStopped = false;

    /**
     * @var array<string, mixed> Additional metadata
     */
    private array $metadata = [];

    /**
     * Create a new event.
     *
     * @param string $name The event name
     * @param array<string, mixed> $data The event data
     * @param string $source The event source
     * @param bool $stoppable Whether the event can be stopped
     * @param DateTimeInterface|null $timestamp The event timestamp
     */
    public function __construct(
        private readonly string $name,
        private readonly array $data = [],
        private readonly string $source = 'unknown',
        private readonly bool $stoppable = true,
        private readonly ?DateTimeInterface $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? new DateTime();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }

    /**
     * {@inheritdoc}
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * {@inheritdoc}
     */
    public function isStoppable(): bool
    {
        return $this->stoppable;
    }

    /**
     * {@inheritdoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * {@inheritdoc}
     */
    public function stopPropagation(): void
    {
        if ($this->stoppable) {
            $this->propagationStopped = true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }
}
