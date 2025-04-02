<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Swarm;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Message\MessageInterface;

/**
 * Interface for coordinating interactions between agents within a Swarm.
 */
interface SwarmCoordinatorInterface
{
    /**
     * Coordinate the next step or interaction within the swarm.
     *
     * @param SwarmInterface $swarm The swarm instance being coordinated.
     * @param mixed $input Optional input for the coordination step.
     * @return mixed The result of the coordination step (e.g., a message, a task result).
     */
    public function coordinate(SwarmInterface $swarm, mixed $input = null): mixed;

    /**
     * Assign a task to a specific agent or a group of agents.
     *
     * @param SwarmInterface $swarm The swarm context.
     * @param AgentInterface|string $agentOrId The agent instance or ID to assign the task to.
     * @param mixed $task The task details or description.
     * @return mixed The result of the task assignment.
     */
    public function assignTask(SwarmInterface $swarm, AgentInterface|string $agentOrId, mixed $task): mixed;

    /**
     * Route a message to the appropriate agent(s) within the swarm.
     *
     * @param SwarmInterface $swarm The swarm context.
     * @param MessageInterface $message The message to route.
     * @param AgentInterface|string|null $recipientAgentOrId Optional specific recipient agent or ID.
     * @return void
     */
    public function routeMessage(SwarmInterface $swarm, MessageInterface $message, AgentInterface|string|null $recipientAgentOrId = null): void;

    /**
     * Broadcast a message to all agents in the swarm.
     *
     * @param SwarmInterface $swarm The swarm context.
     * @param MessageInterface $message The message to broadcast.
     * @param AgentInterface|string|null $senderAgentOrId Optional sender agent or ID to exclude from broadcast.
     * @return void
     */
    public function broadcastMessage(SwarmInterface $swarm, MessageInterface $message, AgentInterface|string|null $senderAgentOrId = null): void;
} 