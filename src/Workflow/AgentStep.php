<?php

declare(strict_types=1);

namespace PhpSwarm\Workflow;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Workflow\WorkflowStepInterface;
use PhpSwarm\Exception\PhpSwarmException;

/**
 * Implementation of a workflow step that uses an agent to perform a task.
 */
class AgentStep implements WorkflowStepInterface
{
    /**
     * @var string The name of the step
     */
    private string $name;

    /**
     * @var string The description of the step
     */
    private string $description;

    /**
     * @var AgentInterface|null The agent to execute this step
     */
    private ?AgentInterface $agent = null;

    /**
     * @var int|null The timeout in seconds
     */
    private ?int $timeout = null;

    /**
     * @var bool Whether this step is required
     */
    private bool $required = true;

    /**
     * @var array<string, string> Input mapping
     */
    private array $inputMapping = [];

    /**
     * @var array<string, string> Output mapping
     */
    private array $outputMapping = [];

    /**
     * @var string The task to execute
     */
    private string $task;

    /**
     * Create a new AgentStep instance.
     *
     * @param string $name The name of the step
     * @param string $task The task to execute
     * @param string $description The description of the step
     * @param AgentInterface|null $agent The agent to execute this step
     */
    public function __construct(
        string $name,
        string $task,
        string $description = '',
        ?AgentInterface $agent = null
    ) {
        $this->name = $name;
        $this->task = $task;
        $this->description = $description;
        $this->agent = $agent;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $input, array $stepsOutput = []): array
    {
        if (!$this->agent) {
            throw new PhpSwarmException("No agent assigned to step '{$this->name}'");
        }

        // Execute the task with the agent
        $response = $this->agent->run($this->interpolateTask($input), $input);

        // Format the response to an associative array
        return [
            'content' => $response->getContent(),
            'metadata' => $response->getMetadata(),
            'execution_time' => $response->getExecutionTime(),
        ];
    }

    /**
     * Interpolate variables in the task description.
     *
     * @param array<string, mixed> $input The input data
     * @return string The interpolated task
     */
    private function interpolateTask(array $input): string
    {
        // Replace {variable} with values from input
        $task = $this->task;

        foreach ($input as $key => $value) {
            // Only interpolate scalar values
            if (is_scalar($value) || is_null($value)) {
                $task = str_replace('{' . $key . '}', (string) $value, $task);
            }
        }

        return $task;
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
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAgent(): ?AgentInterface
    {
        return $this->agent;
    }

    /**
     * {@inheritdoc}
     */
    public function setAgent(AgentInterface $agent): self
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * {@inheritdoc}
     */
    public function setTimeout(?int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getInputMapping(): array
    {
        return $this->inputMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function setInputMapping(array $mapping): self
    {
        $this->inputMapping = $mapping;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOutputMapping(): array
    {
        return $this->outputMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutputMapping(array $mapping): self
    {
        $this->outputMapping = $mapping;
        return $this;
    }

    /**
     * Get the task to execute.
     *
     * @return string The task
     */
    public function getTask(): string
    {
        return $this->task;
    }

    /**
     * Set the task to execute.
     *
     * @param string $task The task
     * @return self
     */
    public function setTask(string $task): self
    {
        $this->task = $task;
        return $this;
    }
}
