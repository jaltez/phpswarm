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
     * @var string The name of the step
     */
    private string $name;
    
    /**
     * @var string The description of the step
     */
    private string $description;
    
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
        string $name,
        callable $function,
        string $description = ''
    ) {
        $this->name = $name;
        $this->function = $function;
        $this->description = $description;
    }
    
    /**
     * {@inheritdoc}
     */
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
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'execution_time' => microtime(true) - $startTime,
            ];
        }
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
        return null; // Function steps don't use agents
    }
    
    /**
     * {@inheritdoc}
     */
    public function setAgent(AgentInterface $agent): self
    {
        // Silently ignore as function steps don't use agents
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
     * @return self
     */
    public function setFunction(callable $function): self
    {
        $this->function = $function;
        return $this;
    }
} 