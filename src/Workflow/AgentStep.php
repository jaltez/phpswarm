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
     * Create a new AgentStep instance.
     *
     * @param string $name The name of the step
     * @param string $task The task to execute
     * @param string $description The description of the step
     * @param AgentInterface|null $agent The agent to execute this step
     */
    public function __construct(private string $name, private string $task, private string $description = '', private ?AgentInterface $agent = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function execute(array $input, array $stepsOutput = []): array
    {
        if (!$this->agent instanceof \PhpSwarm\Contract\Agent\AgentInterface) {
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
    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getAgent(): ?AgentInterface
    {
        return $this->agent;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setAgent(AgentInterface $agent): self
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setTimeout(?int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getInputMapping(): array
    {
        return $this->inputMapping;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setInputMapping(array $mapping): self
    {
        $this->inputMapping = $mapping;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getOutputMapping(): array
    {
        return $this->outputMapping;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
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
     */
    public function setTask(string $task): self
    {
        $this->task = $task;
        return $this;
    }
}
