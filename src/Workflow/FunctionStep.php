<?php

declare(strict_types=1);

namespace PhpSwarm\Workflow;

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Workflow\WorkflowStepInterface;

/**
 * Implementation of a workflow step that executes a PHP function or callback.
 */
class FunctionStep implements WorkflowStepInterface
{
    /**
     * @var callable The function to execute
     */
    private $function;

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
     * Create a new FunctionStep instance.
     *
     * @param string $name The name of the step
     * @param callable $function The function to execute
     * @param string $description The description of the step
     */
    public function __construct(
        private string $name,
        callable $function,
        private string $description = ''
    ) {
        $this->function = $function;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function execute(array $input, array $stepsOutput = []): array
    {
        $startTime = microtime(true);

        try {
            // Execute the function
            $result = ($this->function)($input, $stepsOutput);

            // Ensure the result is an array
            if (!is_array($result)) {
                $result = ['result' => $result];
            }

            // Add execution time
            $result['execution_time'] = microtime(true) - $startTime;

            return $result;
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
                'execution_time' => microtime(true) - $startTime,
            ];
        }
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
        return null; // Function steps don't use agents
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setAgent(AgentInterface $agent): self
    {
        // Silently ignore as function steps don't use agents
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
     * Get the function to execute.
     *
     * @return callable The function
     */
    public function getFunction(): callable
    {
        return $this->function;
    }

    /**
     * Set the function to execute.
     *
     * @param callable $function The function
     */
    public function setFunction(callable $function): self
    {
        $this->function = $function;
        return $this;
    }
}
