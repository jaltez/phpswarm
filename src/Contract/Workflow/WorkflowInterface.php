<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Workflow;

use PhpSwarm\Contract\Agent\AgentInterface;

/**
 * Interface for workflow engines in the PHPSwarm system.
 *
 * A workflow represents a sequence of steps that can be executed
 * by one or more agents to achieve a complex goal.
 */
interface WorkflowInterface
{
    /**
     * Add a step to the workflow.
     *
     * @param string $stepId Unique identifier for the step
     * @param WorkflowStepInterface $step The step to add
     */
    public function addStep(string $stepId, WorkflowStepInterface $step): self;

    /**
     * Add a dependency between steps.
     *
     * @param string $stepId The step that depends on another
     * @param string $dependsOnStepId The step that must complete first
     */
    public function addDependency(string $stepId, string $dependsOnStepId): self;

    /**
     * Set multiple dependencies for a step.
     *
     * @param string $stepId The step to set dependencies for
     * @param array<string> $dependsOnStepIds The steps that must complete first
     */
    public function setDependencies(string $stepId, array $dependsOnStepIds): self;

    /**
     * Get all steps in the workflow.
     *
     * @return array<string, WorkflowStepInterface> The steps, keyed by step ID
     */
    public function getSteps(): array;

    /**
     * Get a specific step by its ID.
     *
     * @param string $stepId The step ID
     * @return WorkflowStepInterface|null The step, or null if not found
     */
    public function getStep(string $stepId): ?WorkflowStepInterface;

    /**
     * Get the dependencies for a step.
     *
     * @param string $stepId The step ID
     * @return array<string> The IDs of steps this step depends on
     */
    public function getDependencies(string $stepId): array;

    /**
     * Execute the entire workflow.
     *
     * @param array<string, mixed> $input Initial input data for the workflow
     * @return WorkflowResultInterface The result of the workflow execution
     */
    public function execute(array $input = []): WorkflowResultInterface;

    /**
     * Get the name of the workflow.
     *
     * @return string The workflow name
     */
    public function getName(): string;

    /**
     * Set the name of the workflow.
     *
     * @param string $name The workflow name
     */
    public function setName(string $name): self;

    /**
     * Get the description of the workflow.
     *
     * @return string The workflow description
     */
    public function getDescription(): string;

    /**
     * Set the description of the workflow.
     *
     * @param string $description The workflow description
     */
    public function setDescription(string $description): self;

    /**
     * Set the maximum number of parallel steps.
     *
     * @param int $maxParallelSteps The maximum number of steps to run in parallel
     */
    public function setMaxParallelSteps(int $maxParallelSteps): self;

    /**
     * Get the maximum number of parallel steps.
     *
     * @return int The maximum number of steps to run in parallel
     */
    public function getMaxParallelSteps(): int;
}
