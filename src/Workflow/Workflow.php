<?php

declare(strict_types=1);

namespace PhpSwarm\Workflow;

use PhpSwarm\Contract\Logger\LoggerInterface;
use PhpSwarm\Contract\Logger\MonitorInterface;
use PhpSwarm\Contract\Workflow\WorkflowInterface;
use PhpSwarm\Contract\Workflow\WorkflowResultInterface;
use PhpSwarm\Contract\Workflow\WorkflowStepInterface;
use PhpSwarm\Exception\PhpSwarmException;

/**
 * Implementation of a workflow engine for orchestrating agent operations.
 */
class Workflow implements WorkflowInterface
{
    /**
     * @var string The name of the workflow
     */
    private string $name;
    
    /**
     * @var string The description of the workflow
     */
    private string $description;
    
    /**
     * @var array<string, WorkflowStepInterface> The steps in this workflow
     */
    private array $steps = [];
    
    /**
     * @var array<string, array<string>> The dependencies between steps
     */
    private array $dependencies = [];
    
    /**
     * @var int The maximum number of steps to run in parallel
     */
    private int $maxParallelSteps = 1;
    
    /**
     * @var LoggerInterface|null The logger to use
     */
    private ?LoggerInterface $logger;
    
    /**
     * @var MonitorInterface|null The performance monitor to use
     */
    private ?MonitorInterface $monitor;
    
    /**
     * Create a new Workflow instance.
     *
     * @param string $name The name of the workflow
     * @param string $description The description of the workflow
     * @param LoggerInterface|null $logger Optional logger
     * @param MonitorInterface|null $monitor Optional performance monitor
     */
    public function __construct(
        string $name,
        string $description = '',
        ?LoggerInterface $logger = null,
        ?MonitorInterface $monitor = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->logger = $logger;
        $this->monitor = $monitor;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addStep(string $stepId, WorkflowStepInterface $step): self
    {
        if (isset($this->steps[$stepId])) {
            throw new PhpSwarmException("Step with ID '$stepId' already exists in workflow");
        }
        
        $this->steps[$stepId] = $step;
        $this->dependencies[$stepId] = [];
        
        if ($this->logger) {
            $this->logger->debug("Added step '$stepId' to workflow", [
                'workflow' => $this->name,
                'step_name' => $step->getName(),
            ]);
        }
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function addDependency(string $stepId, string $dependsOnStepId): self
    {
        if (!isset($this->steps[$stepId])) {
            throw new PhpSwarmException("Step with ID '$stepId' does not exist in workflow");
        }
        
        if (!isset($this->steps[$dependsOnStepId])) {
            throw new PhpSwarmException("Step with ID '$dependsOnStepId' does not exist in workflow");
        }
        
        if ($stepId === $dependsOnStepId) {
            throw new PhpSwarmException("Step cannot depend on itself");
        }
        
        // Check for circular dependencies
        if ($this->wouldCreateCircularDependency($stepId, $dependsOnStepId)) {
            throw new PhpSwarmException("Adding this dependency would create a circular reference");
        }
        
        if (!in_array($dependsOnStepId, $this->dependencies[$stepId], true)) {
            $this->dependencies[$stepId][] = $dependsOnStepId;
            
            if ($this->logger) {
                $this->logger->debug("Added dependency: '$stepId' depends on '$dependsOnStepId'", [
                    'workflow' => $this->name,
                ]);
            }
        }
        
        return $this;
    }
    
    /**
     * Check if adding a dependency would create a circular reference.
     *
     * @param string $stepId The step that would depend on another
     * @param string $dependsOnStepId The step that would be depended on
     * @return bool True if this would create a circular dependency
     */
    private function wouldCreateCircularDependency(string $stepId, string $dependsOnStepId): bool
    {
        // If B depends on A, and we're trying to make A depend on B, that's circular
        return $this->isDependentOn($dependsOnStepId, $stepId);
    }
    
    /**
     * Check if one step is dependent on another, directly or indirectly.
     *
     * @param string $stepId The step to check
     * @param string $potentialDependency The potential dependency
     * @param array<string> $visited Steps already visited (to prevent infinite recursion)
     * @return bool True if stepId depends on potentialDependency
     */
    private function isDependentOn(string $stepId, string $potentialDependency, array $visited = []): bool
    {
        if (in_array($stepId, $visited, true)) {
            return false; // Already checked this path
        }
        
        $visited[] = $stepId;
        
        // Direct dependency
        if (in_array($potentialDependency, $this->dependencies[$stepId] ?? [], true)) {
            return true;
        }
        
        // Check indirect dependencies
        foreach ($this->dependencies[$stepId] ?? [] as $dependency) {
            if ($this->isDependentOn($dependency, $potentialDependency, $visited)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setDependencies(string $stepId, array $dependsOnStepIds): self
    {
        if (!isset($this->steps[$stepId])) {
            throw new PhpSwarmException("Step with ID '$stepId' does not exist in workflow");
        }
        
        // Clear existing dependencies
        $this->dependencies[$stepId] = [];
        
        // Add each dependency
        foreach ($dependsOnStepIds as $dependsOnStepId) {
            $this->addDependency($stepId, $dependsOnStepId);
        }
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSteps(): array
    {
        return $this->steps;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getStep(string $stepId): ?WorkflowStepInterface
    {
        return $this->steps[$stepId] ?? null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDependencies(string $stepId): array
    {
        return $this->dependencies[$stepId] ?? [];
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute(array $input = []): WorkflowResultInterface
    {
        $startTime = microtime(true);
        $processId = null;
        
        if ($this->monitor) {
            $processId = $this->monitor->beginProcess("workflow.{$this->name}", [
                'workflow' => $this->name,
                'steps_count' => count($this->steps),
            ]);
        }
        
        if ($this->logger) {
            $this->logger->info("Starting workflow '{$this->name}'", [
                'steps_count' => count($this->steps),
                'input_keys' => array_keys($input),
            ]);
        }
        
        try {
            $result = $this->executeWorkflow($input, $startTime);
            
            if ($this->monitor && $processId) {
                $this->monitor->endProcess($processId, [
                    'execution_time' => $result->getExecutionTime(),
                    'success' => $result->isSuccessful(),
                    'errors' => $result->getErrors(),
                ]);
            }
            
            if ($this->logger) {
                $status = $result->isSuccessful() ? 'successfully' : 'with errors';
                $this->logger->info("Workflow '{$this->name}' completed $status in {$result->getExecutionTime()}s", [
                    'success' => $result->isSuccessful(),
                    'execution_time' => $result->getExecutionTime(),
                    'errors_count' => count($result->getErrors()),
                ]);
            }
            
            return $result;
        } catch (\Throwable $e) {
            $executionTime = microtime(true) - $startTime;
            
            if ($this->monitor && $processId) {
                $this->monitor->failProcess($processId, $e->getMessage(), [
                    'execution_time' => $executionTime,
                    'exception' => get_class($e),
                ]);
            }
            
            if ($this->logger) {
                $this->logger->error("Workflow '{$this->name}' failed: {$e->getMessage()}", [
                    'execution_time' => $executionTime,
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            
            // Create a failed result
            return new WorkflowResult(
                false,
                [],
                $executionTime,
                [],
                ['workflow' => $e->getMessage()],
                [],
                array_keys($this->steps),
                [
                    [
                        'time' => microtime(true),
                        'level' => 'error',
                        'message' => "Workflow execution failed: {$e->getMessage()}",
                        'exception' => get_class($e),
                    ]
                ]
            );
        }
    }
    
    /**
     * Execute the workflow and return the result.
     *
     * @param array<string, mixed> $input The input data
     * @param float $startTime The workflow start time
     * @return WorkflowResultInterface The workflow result
     */
    private function executeWorkflow(array $input, float $startTime): WorkflowResultInterface
    {
        // Initialize tracking variables
        $stepResults = [];
        $stepErrors = [];
        $skippedSteps = [];
        $executionLog = [];
        
        // Find all steps that have no dependencies
        $readySteps = $this->findStepsWithoutDependencies();
        $remainingSteps = array_diff(array_keys($this->steps), $readySteps);
        $completedSteps = [];
        
        // Execute until all steps are completed or we can't proceed further
        while (!empty($readySteps)) {
            $currentBatch = array_slice($readySteps, 0, $this->maxParallelSteps);
            $readySteps = array_diff($readySteps, $currentBatch);
            
            foreach ($currentBatch as $stepId) {
                $step = $this->steps[$stepId];
                
                $this->logStepExecution($executionLog, $stepId, 'starting');
                
                try {
                    // Prepare input for this step
                    $stepInput = $this->prepareStepInput($stepId, $input, $stepResults);
                    
                    // Execute the step
                    $timerId = null;
                    if ($this->monitor) {
                        $timerId = $this->monitor->startTimer("workflow_step.{$stepId}", [
                            'workflow' => $this->name,
                            'step' => $stepId,
                        ]);
                    }
                    
                    if ($this->logger) {
                        $this->logger->info("Executing workflow step '$stepId'", [
                            'workflow' => $this->name,
                            'step_name' => $step->getName(),
                        ]);
                    }
                    
                    $stepResult = $step->execute($stepInput, $stepResults);
                    
                    if ($this->monitor && $timerId) {
                        $this->monitor->stopTimer($timerId, [
                            'success' => true,
                        ]);
                    }
                    
                    // Store the result
                    $stepResults[$stepId] = $stepResult;
                    $completedSteps[] = $stepId;
                    
                    $this->logStepExecution($executionLog, $stepId, 'completed', null, $stepResult);
                    
                    if ($this->logger) {
                        $this->logger->info("Workflow step '$stepId' completed successfully", [
                            'workflow' => $this->name,
                            'step_name' => $step->getName(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Handle step failure
                    $error = "Step $stepId failed: " . $e->getMessage();
                    $stepErrors[$stepId] = $error;
                    $completedSteps[] = $stepId;
                    
                    if ($this->monitor && isset($timerId)) {
                        $this->monitor->stopTimer($timerId, [
                            'success' => false,
                            'error' => $error,
                        ]);
                    }
                    
                    $this->logStepExecution($executionLog, $stepId, 'failed', $error);
                    
                    if ($this->logger) {
                        $this->logger->error("Workflow step '$stepId' failed: {$e->getMessage()}", [
                            'workflow' => $this->name,
                            'step_name' => $step->getName(),
                            'exception' => get_class($e),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                    
                    // If this step is required and failed, we may need to skip dependent steps
                    if ($step->isRequired()) {
                        $dependentSteps = $this->findDependentSteps($stepId);
                        foreach ($dependentSteps as $dependentStepId) {
                            if (!in_array($dependentStepId, $skippedSteps, true)) {
                                $skippedSteps[] = $dependentStepId;
                                $remainingSteps = array_diff($remainingSteps, [$dependentStepId]);
                                
                                $this->logStepExecution($executionLog, $dependentStepId, 'skipped', "Depends on failed step $stepId");
                                
                                if ($this->logger) {
                                    $this->logger->notice("Skipping workflow step '$dependentStepId' due to dependency failure", [
                                        'workflow' => $this->name,
                                        'failed_dependency' => $stepId,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            
            // Find steps that are now ready to execute
            $readySteps = array_merge($readySteps, $this->findReadySteps($completedSteps, $skippedSteps, $remainingSteps));
            $remainingSteps = array_diff($remainingSteps, $readySteps);
        }
        
        // Check for any remaining steps (they must be unreachable due to dependencies)
        foreach ($remainingSteps as $stepId) {
            if (!in_array($stepId, $skippedSteps, true)) {
                $skippedSteps[] = $stepId;
                
                $this->logStepExecution($executionLog, $stepId, 'skipped', "Unreachable due to dependency graph");
                
                if ($this->logger) {
                    $this->logger->notice("Skipping workflow step '$stepId' due to unreachable dependencies", [
                        'workflow' => $this->name,
                    ]);
                }
            }
        }
        
        // Prepare the final output
        $output = $this->prepareWorkflowOutput($stepResults);
        $executionTime = microtime(true) - $startTime;
        $success = empty($stepErrors) || $this->canCompleteWithErrors($stepErrors, $skippedSteps);
        
        return new WorkflowResult(
            $success,
            $output,
            $executionTime,
            $stepResults,
            $stepErrors,
            $completedSteps,
            $skippedSteps,
            $executionLog
        );
    }
    
    /**
     * Find all steps that have no dependencies.
     *
     * @return array<string> The IDs of steps with no dependencies
     */
    private function findStepsWithoutDependencies(): array
    {
        $noDepSteps = [];
        
        foreach ($this->steps as $stepId => $step) {
            if (empty($this->dependencies[$stepId])) {
                $noDepSteps[] = $stepId;
            }
        }
        
        return $noDepSteps;
    }
    
    /**
     * Find steps that are dependent on a given step.
     *
     * @param string $stepId The step ID to find dependents for
     * @return array<string> The IDs of dependent steps
     */
    private function findDependentSteps(string $stepId): array
    {
        $dependentSteps = [];
        
        foreach ($this->dependencies as $otherStepId => $dependencies) {
            if (in_array($stepId, $dependencies, true)) {
                $dependentSteps[] = $otherStepId;
                
                // Also include transitive dependencies
                $indirectDependents = $this->findDependentSteps($otherStepId);
                $dependentSteps = array_merge($dependentSteps, $indirectDependents);
            }
        }
        
        return array_unique($dependentSteps);
    }
    
    /**
     * Find steps that are now ready to execute.
     *
     * @param array<string> $completedSteps The IDs of completed steps
     * @param array<string> $skippedSteps The IDs of skipped steps
     * @param array<string> $remainingSteps The IDs of remaining steps
     * @return array<string> The IDs of steps that are now ready
     */
    private function findReadySteps(array $completedSteps, array $skippedSteps, array $remainingSteps): array
    {
        $readySteps = [];
        
        foreach ($remainingSteps as $stepId) {
            if (in_array($stepId, $skippedSteps, true)) {
                continue; // Skip already-skipped steps
            }
            
            $dependencies = $this->dependencies[$stepId];
            $allDependenciesMet = true;
            
            foreach ($dependencies as $dependencyId) {
                if (!in_array($dependencyId, $completedSteps, true)) {
                    $allDependenciesMet = false;
                    break;
                }
            }
            
            if ($allDependenciesMet) {
                $readySteps[] = $stepId;
            }
        }
        
        return $readySteps;
    }
    
    /**
     * Prepare input for a step based on its input mapping.
     *
     * @param string $stepId The step ID
     * @param array<string, mixed> $workflowInput The workflow input data
     * @param array<string, array<string, mixed>> $stepResults Results from previous steps
     * @return array<string, mixed> The prepared input for the step
     */
    private function prepareStepInput(string $stepId, array $workflowInput, array $stepResults): array
    {
        $step = $this->steps[$stepId];
        $inputMapping = $step->getInputMapping();
        $stepInput = [];
        
        // Start with workflow input based on mapping
        foreach ($inputMapping as $workflowKey => $stepKey) {
            if (isset($workflowInput[$workflowKey])) {
                $stepInput[$stepKey] = $workflowInput[$workflowKey];
            }
        }
        
        // Add any additional context from workflow input if not already set
        foreach ($workflowInput as $key => $value) {
            if (!isset($stepInput[$key])) {
                $stepInput[$key] = $value;
            }
        }
        
        return $stepInput;
    }
    
    /**
     * Prepare the workflow output based on step results and output mappings.
     *
     * @param array<string, array<string, mixed>> $stepResults The results from all steps
     * @return array<string, mixed> The prepared workflow output
     */
    private function prepareWorkflowOutput(array $stepResults): array
    {
        $output = [];
        
        foreach ($this->steps as $stepId => $step) {
            if (!isset($stepResults[$stepId])) {
                continue; // Skip steps that weren't executed
            }
            
            $stepResult = $stepResults[$stepId];
            $outputMapping = $step->getOutputMapping();
            
            // Map step output to workflow output
            foreach ($outputMapping as $stepKey => $workflowKey) {
                if (isset($stepResult[$stepKey])) {
                    $output[$workflowKey] = $stepResult[$stepKey];
                }
            }
            
            // Add step results to output if not already mapped
            if (empty($outputMapping)) {
                $output[$stepId] = $stepResult;
            }
        }
        
        return $output;
    }
    
    /**
     * Log a step execution event.
     *
     * @param array<int, array<string, mixed>> $log The log to append to
     * @param string $stepId The step ID
     * @param string $status The step status
     * @param string|null $error The error message, if any
     * @param array<string, mixed>|null $result The step result, if any
     */
    private function logStepExecution(array &$log, string $stepId, string $status, ?string $error = null, ?array $result = null): void
    {
        $entry = [
            'time' => microtime(true),
            'step' => $stepId,
            'status' => $status,
        ];
        
        if ($error !== null) {
            $entry['error'] = $error;
        }
        
        if ($result !== null) {
            $entry['result'] = $result;
        }
        
        $log[] = $entry;
    }
    
    /**
     * Check if the workflow can complete successfully despite errors.
     *
     * @param array<string, string> $stepErrors The step errors
     * @param array<string> $skippedSteps The skipped steps
     * @return bool True if the workflow can still be considered successful
     */
    private function canCompleteWithErrors(array $stepErrors, array $skippedSteps): bool
    {
        // Check if any failed steps were required
        foreach (array_keys($stepErrors) as $failedStepId) {
            $step = $this->steps[$failedStepId];
            if ($step->isRequired()) {
                return false;
            }
        }
        
        // Check if any skipped steps were required
        foreach ($skippedSteps as $skippedStepId) {
            $step = $this->steps[$skippedStepId];
            if ($step->isRequired()) {
                return false;
            }
        }
        
        return true;
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
    public function setMaxParallelSteps(int $maxParallelSteps): self
    {
        if ($maxParallelSteps < 1) {
            throw new PhpSwarmException("Maximum parallel steps must be at least 1");
        }
        
        $this->maxParallelSteps = $maxParallelSteps;
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getMaxParallelSteps(): int
    {
        return $this->maxParallelSteps;
    }
} 