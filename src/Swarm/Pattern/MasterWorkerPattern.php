<?php

declare(strict_types=1);

namespace PhpSwarm\Swarm\Pattern;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Message\MessageInterface;
use PhpSwarm\Contract\Swarm\SwarmCoordinatorInterface;
use PhpSwarm\Contract\Swarm\SwarmInterface;
use PhpSwarm\Message\Message;

/**
 * Master-Worker Pattern for agent collaboration.
 * 
 * In this pattern, a designated "master" agent breaks down tasks and
 * distributes them to "worker" agents, then aggregates their results.
 */
class MasterWorkerPattern implements SwarmCoordinatorInterface
{
    /**
     * @var string The ID of the master agent.
     */
    private string $masterAgentId;

    /**
     * Constructor.
     *
     * @param string $masterAgentId The ID of the agent that will act as the master.
     */
    public function __construct(string $masterAgentId)
    {
        $this->masterAgentId = $masterAgentId;
    }

    /**
     * {@inheritdoc}
     */
    public function coordinate(SwarmInterface $swarm, mixed $input = null): mixed
    {
        // Get the master agent
        $masterAgent = $swarm->getAgent($this->masterAgentId);
        if (!$masterAgent) {
            throw new \RuntimeException("Master agent with ID {$this->masterAgentId} not found in swarm.");
        }

        // If input is a Message, route it
        if ($input instanceof MessageInterface) {
            $this->routeMessage($swarm, $input);
            return $input;
        }

        // If input is a string, treat it as a task for the master agent
        if (is_string($input) && !empty($input)) {
            // Step 1: Master agent analyzes the task
            $masterResponse = $masterAgent->run("Analyze this task and break it down into subtasks: {$input}");

            // Parse the master's response to get subtasks
            $subtasks = $this->parseSubtasksFromResponse($masterResponse->getFinalAnswer());

            // Handle edge case: no subtasks found
            if (empty($subtasks)) {
                // If no subtasks were identified, let the master handle the entire task
                return $masterAgent->run($input);
            }

            // Step 2: Distribute subtasks to workers
            $workers = $this->getWorkerAgents($swarm);

            // Handle edge case: no worker agents available
            if (empty($workers)) {
                // If no workers are available, the master has to do all the work
                $results = [];
                foreach ($subtasks as $subtask) {
                    $response = $masterAgent->run($subtask);
                    $results[] = $response->getFinalAnswer();
                }
            } else {
                // Distribute subtasks to workers
                $results = [];
                foreach ($subtasks as $index => $subtask) {
                    // Simple round-robin distribution
                    $workerIndex = $index % count($workers);
                    $worker = $workers[$workerIndex];

                    // Assign the subtask to the worker
                    $workerResponse = $this->assignTask($swarm, $worker, $subtask);
                    $results[] = $workerResponse->getFinalAnswer();
                }
            }

            // Step 3: Master aggregates results
            $aggregationTask = "Aggregate these results into a final answer: " . implode(" | ", $results);
            $finalResponse = $masterAgent->run($aggregationTask);

            return $finalResponse;
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
                throw new \InvalidArgumentException("Agent with ID {$agentOrId} not found in swarm.");
            }
        }

        // Convert the task to a string if it's not already
        $taskString = is_string($task) ? $task : json_encode($task);

        // Create a message for this task assignment
        $message = new Message(
            $this->masterAgentId, // Master is the sender
            [$agent->getName()], // Recipient is the target worker
            $taskString,
            'task',
            ['assigned_at' => time()]
        );

        // Add message to swarm history
        $swarm->addMessage($message);

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

        // Default implementation
        if ($recipientAgentOrId !== null) {
            // Message is for a specific agent
            return;
        }

        // In master-worker pattern, all messages go through the master
        $recipientIds = $message->getRecipientIds();

        if (empty($recipientIds) || in_array($this->masterAgentId, $recipientIds, true)) {
            // Message for master or broadcast
            return;
        }

        // For messages to workers, we'll let the message pass directly
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

        // In master-worker pattern, broadcasts are controlled by the master
        // Here, we simply record the message in the swarm's history
    }

    /**
     * Parse subtasks from the master agent's response.
     * In a real implementation, this would be more sophisticated.
     *
     * @param string $response The master agent's response
     * @return array<string> An array of subtask strings
     */
    private function parseSubtasksFromResponse(string $response): array
    {
        // Very simple parsing - split by numbered list items
        $subtasks = [];

        // Look for markdown-style numbered lists like "1. Task one"
        preg_match_all('/\d+\.\s+([^\n]+)/', $response, $matches);

        if (!empty($matches[1])) {
            $subtasks = $matches[1];
        } else {
            // Fallback - just split by newlines
            $subtasks = array_filter(array_map('trim', explode("\n", $response)));
        }

        return $subtasks;
    }

    /**
     * Get all worker agents (non-master agents) from the swarm.
     *
     * @param SwarmInterface $swarm The swarm instance
     * @return array<AgentInterface> An array of worker agents
     */
    private function getWorkerAgents(SwarmInterface $swarm): array
    {
        $workers = [];
        foreach ($swarm->getAgents() as $agentId => $agent) {
            if ($agentId !== $this->masterAgentId) {
                $workers[] = $agent;
            }
        }
        return $workers;
    }
}
