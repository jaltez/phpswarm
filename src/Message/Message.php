<?php

declare(strict_types=1);

namespace PhpSwarm\Message;

use PhpSwarm\Contract\Message\MessageInterface;

/**
 * Default implementation of the MessageInterface for agent communication.
 */
class Message implements MessageInterface
{
    /**
     * @var string
     */
    private string $id;

    /**
     * @var string
     */
    private string $senderId;

    /**
     * @var array<string>
     */
    private array $recipientIds;

    /**
     * @var string
     */
    private string $content;

    /**
     * @var \DateTimeImmutable
     */
    private \DateTimeImmutable $timestamp;

    /**
     * @var string
     */
    private string $type;

    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * Message constructor.
     *
     * @param string $senderId The ID of the sender agent.
     * @param array<string> $recipientIds The IDs of the recipient agents (empty array for broadcast).
     * @param string $content The content of the message.
     * @param string $type The type of the message (e.g., 'task', 'response').
     * @param array<string, mixed> $metadata Optional additional metadata.
     */
    public function __construct(
        string $senderId,
        array $recipientIds,
        string $content,
        string $type = 'info',
        array $metadata = []
    ) {
        $this->id = $this->generateUniqueId();
        $this->senderId = $senderId;
        $this->recipientIds = $recipientIds;
        $this->content = $content;
        $this->timestamp = new \DateTimeImmutable();
        $this->type = $type;
        $this->metadata = $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getSenderId(): string
    {
        return $this->senderId;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecipientIds(): array
    {
        return $this->recipientIds;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Generate a unique identifier for the message.
     *
     * @return string The generated unique ID.
     */
    private function generateUniqueId(): string
    {
        return bin2hex(random_bytes(16));
    }
} 