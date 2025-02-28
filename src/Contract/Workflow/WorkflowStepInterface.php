<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Workflow;

use PhpSwarm\Contract\Agent\AgentInterface;

/**
 * Interface for a single step in a workflow.
 *
 * A workflow step represents a discrete task or operation that can be
 * performed by an agent as part of a larger workflow.
 */
interface WorkflowStepInterface
{
    /**
     * Execute this workflow step.
     *
     * @param array<string, mixed> $input Input data for this step
     * @param array<string, mixed> $stepsOutput Output from previous steps
     * @return array<string, mixed> The result of this step
     */
    public function execute(array $input, array $stepsOutput = []): array;

    /**
     * Get the name of this step.
     *
     * @return string The step name
     */
    public function getName(): string;

    /**
     * Set the name of this step.
     *
     * @param string $name The step name
     * @return self
     */
    public function setName(string $name): self;

    /**
     * Get the description of this step.
     *
     * @return string The step description
     */
    public function getDescription(): string;

    /**
     * Set the description of this step.
     *
     * @param string $description The step description
     * @return self
     */
    public function setDescription(string $description): self;

    /**
     * Get the agent assigned to this step.
     *
     * @return AgentInterface|null The agent, or null if none assigned
     */
    public function getAgent(): ?AgentInterface;

    /**
     * Set the agent for this step.
     *
     * @param AgentInterface $agent The agent to assign
     * @return self
     */
    public function setAgent(AgentInterface $agent): self;

    /**
     * Get the maximum execution time for this step in seconds.
     *
     * @return int|null The timeout in seconds, or null for no timeout
     */
    public function getTimeout(): ?int;

    /**
     * Set the maximum execution time for this step in seconds.
     *
     * @param int|null $timeout The timeout in seconds, or null for no timeout
     * @return self
     */
    public function setTimeout(?int $timeout): self;

    /**
     * Get whether this step is required for workflow completion.
     *
     * @return bool True if the step is required, false if optional
     */
    public function isRequired(): bool;

    /**
     * Set whether this step is required for workflow completion.
     *
     * @param bool $required True if the step is required, false if optional
     * @return self
     */
    public function setRequired(bool $required): self;

    /**
     * Get the mapping of input data from workflow input to step input.
     *
     * @return array<string, string> Mapping of workflow input keys to step input keys
     */
    public function getInputMapping(): array;

    /**
     * Set the mapping of input data from workflow input to step input.
     *
     * @param array<string, string> $mapping Mapping of workflow input keys to step input keys
     * @return self
     */
    public function setInputMapping(array $mapping): self;

    /**
     * Get the mapping of output data from step output to workflow output.
     *
     * @return array<string, string> Mapping of step output keys to workflow output keys
     */
    public function getOutputMapping(): array;

    /**
     * Set the mapping of output data from step output to workflow output.
     *
     * @param array<string, string> $mapping Mapping of step output keys to workflow output keys
     * @return self
     */
    public function setOutputMapping(array $mapping): self;
}
