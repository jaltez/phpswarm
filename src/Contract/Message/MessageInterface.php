<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Message;

/**
 * Interface for messages exchanged between agents in a swarm.
 */
interface MessageInterface
{
    /**
     * Get the unique identifier of the message.
     *
     * @return string The message ID.
     */
    public function getId(): string;

    /**
     * Get the ID of the sender agent.
     *
     * @return string The sender agent ID.
     */
    public function getSenderId(): string;

    /**
     * Get the ID of the recipient agent(s).
     * An empty array means broadcast to all agents.
     *
     * @return array<string> The recipient agent IDs.
     */
    public function getRecipientIds(): array;

    /**
     * Get the content of the message.
     *
     * @return string The message content.
     */
    public function getContent(): string;

    /**
     * Get the timestamp when the message was created.
     *
     * @return \DateTimeImmutable The message creation timestamp.
     */
    public function getTimestamp(): \DateTimeImmutable;

    /**
     * Get the message type (e.g., 'task', 'response', 'info', 'query').
     *
     * @return string The message type.
     */
    public function getType(): string;

    /**
     * Get any additional metadata associated with the message.
     *
     * @return array<string, mixed> The message metadata.
     */
    public function getMetadata(): array;
} 