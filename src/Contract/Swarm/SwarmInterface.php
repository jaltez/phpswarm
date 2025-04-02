<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Swarm;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Message\MessageInterface;

/**
 * Interface for managing a collection of Agents working together.
 */
interface SwarmInterface
{
    /**
     * Add an agent to the swarm.
     *
     * @param AgentInterface $agent The agent to add.
     * @return void
     */
    public function addAgent(AgentInterface $agent): void;

    /**
     * Remove an agent from the swarm.
     *
     * @param string $agentId The ID of the agent to remove.
     * @return bool True if the agent was removed, false otherwise.
     */
    public function removeAgent(string $agentId): bool;

    /**
     * Get all agents currently in the swarm.
     *
     * @return array<string, AgentInterface> An associative array of agent IDs to AgentInterface instances.
     */
    public function getAgents(): array;

    /**
     * Get a specific agent by its ID.
     *
     * @param string $agentId The ID of the agent.
     * @return AgentInterface|null The agent instance or null if not found.
     */
    public function getAgent(string $agentId): ?AgentInterface;

    /**
     * Run the swarm to achieve a specific goal or complete a task.
     * The exact execution logic depends on the implementation and the coordinator.
     *
     * @param mixed $initialInput Optional initial input or goal for the swarm.
     * @return mixed The final result or output from the swarm's operation.
     */
    public function run(mixed $initialInput = null): mixed;

    /**
     * Get the history of messages exchanged within the swarm.
     *
     * @return MessageInterface[] An array of message objects.
     */
    public function getMessages(): array;

    /**
     * Add a message to the swarm's message history.
     *
     * @param MessageInterface $message The message to add.
     * @return void
     */
    public function addMessage(MessageInterface $message): void;

    /**
     * Set the coordinator responsible for managing agent interactions.
     *
     * @param SwarmCoordinatorInterface $coordinator The coordinator instance.
     * @return void
     */
    public function setCoordinator(SwarmCoordinatorInterface $coordinator): void;

    /**
     * Get the coordinator instance.
     *
     * @return SwarmCoordinatorInterface|null The coordinator instance or null if not set.
     */
    public function getCoordinator(): ?SwarmCoordinatorInterface;
} 