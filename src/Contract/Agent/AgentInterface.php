<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Agent;

use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\Memory\MemoryInterface;
use PhpSwarm\Contract\Tool\ToolInterface;

/**
 * Interface for all agents in the PHPSwarm system.
 *
 * An agent is an autonomous entity that can perform tasks, make decisions,
 * and use tools to accomplish objectives.
 */
interface AgentInterface
{
    /**
     * Run the agent with a specific task.
     *
     * @param string $task The task to perform
     * @param array<string, mixed> $context Additional context for the task
     * @return AgentResponseInterface The response from the agent
     */
    public function run(string $task, array $context = []): AgentResponseInterface;

    /**
     * Get the name of the agent.
     */
    public function getName(): string;

    /**
     * Get the role of the agent.
     */
    public function getRole(): string;

    /**
     * Get the goal of the agent.
     */
    public function getGoal(): string;

    /**
     * Get the backstory of the agent.
     */
    public function getBackstory(): string;

    /**
     * Get all tools available to the agent.
     *
     * @return array<ToolInterface>
     */
    public function getTools(): array;

    /**
     * Add a tool to the agent.
     *
     * @param ToolInterface $tool The tool to add
     */
    public function addTool(ToolInterface $tool): self;

    /**
     * Set the LLM to be used by the agent.
     *
     * @param LLMInterface $llm The LLM to use
     */
    public function withLLM(LLMInterface $llm): self;

    /**
     * Set the memory system to be used by the agent.
     *
     * @param MemoryInterface $memory The memory system to use
     */
    public function withMemory(MemoryInterface $memory): self;

    /**
     * Enable or disable verbose logging for the agent.
     *
     * @param bool $verbose Whether to enable verbose logging
     */
    public function withVerboseLogging(bool $verbose = true): self;

    /**
     * Set whether the agent can delegate tasks to other agents.
     *
     * @param bool $allowDelegation Whether to allow delegation
     */
    public function allowDelegation(bool $allowDelegation = true): self;
}
