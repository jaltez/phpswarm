<?php

declare(strict_types=1);

namespace PhpSwarm\Swarm;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Message\MessageInterface;
use PhpSwarm\Contract\Swarm\SwarmCoordinatorInterface;
use PhpSwarm\Contract\Swarm\SwarmInterface;
use PhpSwarm\Message\Message;

/**
 * Default implementation of the SwarmCoordinatorInterface.
 * Manages interactions between agents in a swarm.
 */
class SwarmCoordinator implements SwarmCoordinatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function coordinate(SwarmInterface $swarm, mixed $input = null): mixed
    {
        // Get all agents from the swarm
        $agents = $swarm->getAgents();

        if (empty($agents)) {
            return null;
        }

        // If input is a Message, handle routing
        if ($input instanceof MessageInterface) {
            $this->routeMessage($swarm, $input);
            return $input;
        }

        // If input is a string, treat it as a task for the first agent
        if (is_string($input) && !empty($input)) {
            $firstAgent = reset($agents);
            return $this->assignTask($swarm, $firstAgent, $input);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function assignTask(SwarmInterface $swarm, AgentInterface|string $agentOrId, mixed $task): mixed
    {
        // Get the agent instance if an ID was provided
        $agent = $agentOrId;
        if (is_string($agentOrId)) {
            $agent = $swarm->getAgent($agentOrId);
            if (!$agent) {
                throw new \InvalidArgumentException("Agent with ID {$agentOrId} not found in the swarm.");
            }
        }

        // Convert the task to a string if it's not already
        $taskString = is_string($task) ? $task : json_encode($task);

        // Create a message for this task assignment
        $message = new Message(
            'system', // System is the sender
            [$agent->getName()], // Recipient is the target agent
            $taskString,
            'task', // Message type is 'task'
            ['assigned_at' => time()]
        );

        // Route the message to the agent
        // routeMessage will add the message to swarm history
        $this->routeMessage($swarm, $message, $agent);

        // Run the agent with the task
        return $agent->run($taskString);
    }

    /**
     * {@inheritdoc}
     */
    public function routeMessage(
        SwarmInterface $swarm,
        MessageInterface $message,
        AgentInterface|string|null $recipientAgentOrId = null
    ): void {
        // Add message to swarm history
        $swarm->addMessage($message);

        // If a specific recipient is provided, route only to that agent
        if ($recipientAgentOrId !== null) {
            $recipientAgent = $recipientAgentOrId;
            if (is_string($recipientAgentOrId)) {
                $recipientAgent = $swarm->getAgent($recipientAgentOrId);
                if (!$recipientAgent) {
                    throw new \InvalidArgumentException("Agent with ID {$recipientAgentOrId} not found in the swarm.");
                }
            }

            // Message routed to specific agent
            return;
        }

        // If no specific recipient, use the ones specified in the message
        $recipientIds = $message->getRecipientIds();
        if (!empty($recipientIds)) {
            foreach ($recipientIds as $recipientId) {
                $agent = $swarm->getAgent($recipientId);
                if ($agent) {
                    // Message routed to specified agent
                }
            }
        } else {
            // Empty recipient list means broadcast
            $this->broadcastMessage($swarm, $message, $message->getSenderId());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function broadcastMessage(
        SwarmInterface $swarm,
        MessageInterface $message,
        AgentInterface|string|null $senderAgentOrId = null
    ): void {
        // Add message to swarm history
        $swarm->addMessage($message);

        $senderId = $senderAgentOrId;
        if ($senderAgentOrId instanceof AgentInterface) {
            $senderId = $senderAgentOrId->getName();
        }

        // Get all agents
        $agents = $swarm->getAgents();

        // Send message to all agents except the sender
        foreach ($agents as $agentId => $agent) {
            if ($senderId !== null && $agentId === $senderId) {
                continue; // Skip the sender
            }

            // Message broadcasted to agent
        }
    }
}
