<?php

declare(strict_types=1);

namespace PhpSwarm\Agent;

use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\Memory\MemoryInterface;
use PhpSwarm\Contract\Tool\ToolInterface;
use PhpSwarm\Memory\ArrayMemory;

/**
 * Builder for creating Agent instances with a fluent interface.
 */
class AgentBuilder
{
    /**
     * @var string|null Agent name
     */
    private ?string $name = null;

    /**
     * @var string|null Agent role
     */
    private ?string $role = null;

    /**
     * @var string|null Agent goal
     */
    private ?string $goal = null;

    /**
     * @var string Agent backstory
     */
    private string $backstory = '';

    /**
     * @var LLMInterface|null The LLM for this agent
     */
    private ?LLMInterface $llm = null;

    /**
     * @var MemoryInterface|null The memory system
     */
    private ?MemoryInterface $memory = null;

    /**
     * @var array<ToolInterface> Available tools
     */
    private array $tools = [];

    /**
     * @var bool Whether to enable verbose logging
     */
    private bool $verboseLogging = false;

    /**
     * @var bool Whether to allow the agent to delegate tasks
     */
    private bool $allowDelegation = false;

    /**
     * @var int Maximum iterations to run before stopping
     */
    private int $maxIterations = 10;

    /**
     * Set the agent name.
     *
     * @param string $name Agent name
     */
    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the agent role.
     *
     * @param string $role Agent role
     */
    public function withRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    /**
     * Set the agent goal.
     *
     * @param string $goal Agent goal
     */
    public function withGoal(string $goal): self
    {
        $this->goal = $goal;
        return $this;
    }

    /**
     * Set the agent backstory.
     *
     * @param string $backstory Agent backstory
     */
    public function withBackstory(string $backstory): self
    {
        $this->backstory = $backstory;
        return $this;
    }

    /**
     * Set the LLM to be used by the agent.
     *
     * @param LLMInterface $llm The LLM to use
     */
    public function withLLM(LLMInterface $llm): self
    {
        $this->llm = $llm;
        return $this;
    }

    /**
     * Set the memory system to be used by the agent.
     *
     * @param MemoryInterface $memory The memory system to use
     */
    public function withMemory(MemoryInterface $memory): self
    {
        $this->memory = $memory;
        return $this;
    }

    /**
     * Add a tool to the agent.
     *
     * @param ToolInterface $tool The tool to add
     */
    public function addTool(ToolInterface $tool): self
    {
        $this->tools[] = $tool;
        return $this;
    }

    /**
     * Add multiple tools to the agent.
     *
     * @param array<ToolInterface> $tools The tools to add
     */
    public function addTools(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->addTool($tool);
        }
        return $this;
    }

    /**
     * Enable or disable verbose logging for the agent.
     *
     * @param bool $verbose Whether to enable verbose logging
     */
    public function withVerboseLogging(bool $verbose = true): self
    {
        $this->verboseLogging = $verbose;
        return $this;
    }

    /**
     * Set whether the agent can delegate tasks to other agents.
     *
     * @param bool $allowDelegation Whether to allow delegation
     */
    public function allowDelegation(bool $allowDelegation = true): self
    {
        $this->allowDelegation = $allowDelegation;
        return $this;
    }

    /**
     * Set the maximum number of iterations for the agent to run.
     */
    public function withMaxIterations(int $maxIterations): self
    {
        $this->maxIterations = $maxIterations;
        return $this;
    }

    /**
     * Build the Agent instance.
     *
     * @throws \InvalidArgumentException If required properties are missing
     */
    public function build(): Agent
    {
        if (!$this->name) {
            throw new \InvalidArgumentException('Agent name is required');
        }

        if (!$this->role) {
            throw new \InvalidArgumentException('Agent role is required');
        }

        if (!$this->goal) {
            throw new \InvalidArgumentException('Agent goal is required');
        }

        $agent = new Agent(
            $this->name,
            $this->role,
            $this->goal,
            $this->backstory,
            $this->llm,
            $this->memory ?? new ArrayMemory()
        );

        foreach ($this->tools as $tool) {
            $agent->addTool($tool);
        }

        if ($this->verboseLogging) {
            $agent->withVerboseLogging();
        }

        if ($this->allowDelegation) {
            $agent->allowDelegation();
        }

        $agent->withMaxIterations($this->maxIterations);

        return $agent;
    }
}
