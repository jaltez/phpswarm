<?php

declare(strict_types=1);

namespace PhpSwarm\Swarm;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Message\MessageInterface;
use PhpSwarm\Contract\Swarm\SwarmCoordinatorInterface;
use PhpSwarm\Contract\Swarm\SwarmInterface;

/**
 * Default implementation of SwarmInterface.
 * Manages a collection of agents working together to achieve a common goal.
 */
class Swarm implements SwarmInterface
{
    /**
     * @var array<string, AgentInterface>
     */
    private array $agents = [];

    /**
     * @var SwarmCoordinatorInterface|null
     */
    private ?SwarmCoordinatorInterface $coordinator = null;

    /**
     * @var array<MessageInterface>
     */
    private array $messages = [];

    /**
     * Swarm constructor.
     *
     * @param SwarmCoordinatorInterface|null $coordinator Optional coordinator instance.
     * @param array<AgentInterface> $agents Optional initial set of agents.
     */
    public function __construct(
        ?SwarmCoordinatorInterface $coordinator = null,
        array $agents = []
    ) {
        if ($coordinator) {
            $this->coordinator = $coordinator;
        } else {
            // Default to the standard coordinator
            $this->coordinator = new SwarmCoordinator();
        }

        // Add initial agents if provided
        foreach ($agents as $agent) {
            $this->addAgent($agent);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addAgent(AgentInterface $agent): void
    {
        $this->agents[$agent->getName()] = $agent;
    }

    /**
     * {@inheritdoc}
     */
    public function removeAgent(string $agentId): bool
    {
        if (isset($this->agents[$agentId])) {
            unset($this->agents[$agentId]);
            return true;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAgents(): array
    {
        return $this->agents;
    }

    /**
     * {@inheritdoc}
     */
    public function getAgent(string $agentId): ?AgentInterface
    {
        return $this->agents[$agentId] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function run(mixed $initialInput = null): mixed
    {
        if (empty($this->agents)) {
            throw new \RuntimeException('Cannot run swarm with no agents.');
        }

        if (!$this->coordinator) {
            throw new \RuntimeException('Cannot run swarm without a coordinator.');
        }

        // Use the coordinator to run the swarm with the initial input
        return $this->coordinator->coordinate($this, $initialInput);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Add a message to the swarm's message history.
     *
     * @param MessageInterface $message The message to add.
     * @return void
     */
    public function addMessage(MessageInterface $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function setCoordinator(SwarmCoordinatorInterface $coordinator): void
    {
        $this->coordinator = $coordinator;
    }

    /**
     * {@inheritdoc}
     */
    public function getCoordinator(): ?SwarmCoordinatorInterface
    {
        return $this->coordinator;
    }
} 