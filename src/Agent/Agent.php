<?php

declare(strict_types=1);

namespace PhpSwarm\Agent;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Agent\AgentResponseInterface;
use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\Memory\MemoryInterface;
use PhpSwarm\Contract\Tool\ToolInterface;
use PhpSwarm\Exception\Agent\AgentException;
use PhpSwarm\Memory\ArrayMemory;

/**
 * Base implementation of an agent in the PHPSwarm system.
 */
class Agent implements AgentInterface
{
    /**
     * @var string Agent name
     */
    private string $name;

    /**
     * @var string Agent role
     */
    private string $role;

    /**
     * @var string Agent goal
     */
    private string $goal;

    /**
     * @var string Agent backstory
     */
    private string $backstory;

    /**
     * @var LLMInterface|null The LLM for this agent
     */
    private ?LLMInterface $llm = null;

    /**
     * @var MemoryInterface The memory system
     */
    private MemoryInterface $memory;

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
     * Create a new Agent instance.
     *
     * @param string $name Agent name
     * @param string $role Agent role
     * @param string $goal Agent goal
     * @param string $backstory Agent backstory
     * @param LLMInterface|null $llm The LLM to use
     * @param MemoryInterface|null $memory The memory system
     */
    public function __construct(
        string $name,
        string $role,
        string $goal,
        string $backstory = '',
        ?LLMInterface $llm = null,
        ?MemoryInterface $memory = null
    ) {
        $this->name = $name;
        $this->role = $role;
        $this->goal = $goal;
        $this->backstory = $backstory;
        $this->llm = $llm;
        $this->memory = $memory ?? new ArrayMemory();
    }

    /**
     * Create a new Agent builder.
     *
     * @return AgentBuilder
     */
    public static function create(): AgentBuilder
    {
        return new AgentBuilder();
    }

    /**
     * {@inheritdoc}
     */
    public function run(string $task, array $context = []): AgentResponseInterface
    {
        if (!$this->llm) {
            throw new AgentException('No LLM has been set for this agent.');
        }

        $startTime = microtime(true);

        // Placeholder for actual agent logic
        $response = new AgentResponse(
            $task,
            "This is a placeholder response. The agent '{$this->name}' would process task: $task",
            [
                'task' => $task,
                'context' => $context,
                'agent' => $this->name,
            ],
            microtime(true) - $startTime
        );

        return $response;
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
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * {@inheritdoc}
     */
    public function getGoal(): string
    {
        return $this->goal;
    }

    /**
     * {@inheritdoc}
     */
    public function getBackstory(): string
    {
        return $this->backstory;
    }

    /**
     * {@inheritdoc}
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * {@inheritdoc}
     */
    public function addTool(ToolInterface $tool): self
    {
        $this->tools[] = $tool;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withLLM(LLMInterface $llm): self
    {
        $this->llm = $llm;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withMemory(MemoryInterface $memory): self
    {
        $this->memory = $memory;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withVerboseLogging(bool $verbose = true): self
    {
        $this->verboseLogging = $verbose;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function allowDelegation(bool $allowDelegation = true): self
    {
        $this->allowDelegation = $allowDelegation;
        return $this;
    }

    /**
     * Set the maximum number of iterations for the agent to run.
     *
     * @param int $maxIterations
     * @return self
     */
    public function withMaxIterations(int $maxIterations): self
    {
        $this->maxIterations = $maxIterations;
        return $this;
    }

    /**
     * Get the LLM used by this agent.
     *
     * @return LLMInterface|null
     */
    public function getLLM(): ?LLMInterface
    {
        return $this->llm;
    }

    /**
     * Get the memory system used by this agent.
     *
     * @return MemoryInterface
     */
    public function getMemory(): MemoryInterface
    {
        return $this->memory;
    }

    /**
     * Check if verbose logging is enabled.
     *
     * @return bool
     */
    public function isVerboseLoggingEnabled(): bool
    {
        return $this->verboseLogging;
    }

    /**
     * Check if task delegation is allowed.
     *
     * @return bool
     */
    public function isDelegationAllowed(): bool
    {
        return $this->allowDelegation;
    }

    /**
     * Get the maximum number of iterations.
     *
     * @return int
     */
    public function getMaxIterations(): int
    {
        return $this->maxIterations;
    }
}
