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
use PhpSwarm\Console\ConsoleOutput;

/**
 * Base implementation of an agent in the PHPSwarm system.
 */
class Agent implements AgentInterface
{
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
     * @var bool Whether to use colorized console output
     */
    private bool $useColorizedOutput = false;

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
    public function __construct(private readonly string $name, private readonly string $role, private readonly string $goal, private readonly string $backstory = '', private ?LLMInterface $llm = null, private MemoryInterface $memory = new ArrayMemory())
    {
    }

    /**
     * Create a new Agent builder.
     */
    public static function create(): AgentBuilder
    {
        return new AgentBuilder();
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function run(string $task, array $context = []): AgentResponseInterface
    {
        if (!$this->llm instanceof \PhpSwarm\Contract\LLM\LLMInterface) {
            throw new AgentException('No LLM has been set for this agent.');
        }

        $startTime = microtime(true);
        $trace = [];
        
        // Display agent information if colorized output is enabled
        if ($this->useColorizedOutput && (!isset($context['silent']) || $context['silent'] !== true)) {
            // Display agent header
            echo ConsoleOutput::formatAgentInfo(
                $this->name,
                $this->role,
                $this->goal,
                $this->backstory,
                $this->tools
            );
            
            echo ConsoleOutput::info("Processing task: " . $task) . "\n";
        }

        // Build the system prompt based on agent configuration
        $systemPrompt = "You are {$this->name}, a {$this->role}.\n";
        $systemPrompt .= "Your goal is: {$this->goal}\n";
        
        if ($this->backstory !== '' && $this->backstory !== '0') {
            $systemPrompt .= "Backstory: {$this->backstory}\n";
        }
        
        if ($this->tools !== []) {
            $systemPrompt .= "\nYou have the following tools available:\n";
            foreach ($this->tools as $tool) {
                $systemPrompt .= "- " . $tool->getName() . ": " . $tool->getDescription() . "\n";
            }
        }

        // Prepare the messages
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $task
            ]
        ];

        // Add context if needed
        if (isset($context['messages']) && is_array($context['messages'])) {
            $messages = array_merge($messages, $context['messages']);
        }

        // Handle streaming if requested
        if (isset($context['stream']) && $context['stream'] === true && $this->llm->supportsStreaming()) {
            $finalContent = '';
            
            try {
                $this->llm->stream(
                    $messages, 
                    function($chunk) use (&$finalContent, $context): void {
                        // Add to the final content
                        if (isset($chunk['message']['content'])) {
                            $finalContent .= $chunk['message']['content'];
                        } elseif (isset($chunk['response'])) {
                            $finalContent .= $chunk['response'];
                        }
                        
                        // Call the stream callback if provided
                        if (isset($context['streamCallback']) && is_callable($context['streamCallback'])) {
                            $context['streamCallback']($chunk);
                        }
                    },
                    $context
                );
                
                $trace[] = [
                    'type' => 'stream',
                    'messages' => $messages,
                    'final_content' => $finalContent
                ];
                
                // Create the response
                $response = $this->createAgentResponse(
                    $task,
                    $finalContent,
                    $trace,
                    microtime(true) - $startTime,
                    true,
                    null
                );
                
                return $response;
            } catch (\Throwable $e) {
                if ($this->verboseLogging) {
                    error_log("Error during streaming: " . $e->getMessage());
                }
                
                return $this->createAgentResponse(
                    $task,
                    "An error occurred: " . $e->getMessage(),
                    $trace,
                    microtime(true) - $startTime,
                    false,
                    $e->getMessage()
                );
            }
        } else {
            // Use regular chat completion
            try {
                $llmResponse = $this->llm->chat($messages, $context);
                
                $trace[] = [
                    'type' => 'chat',
                    'messages' => $messages,
                    'response' => $llmResponse->getRawResponse()
                ];
                
                // Create token usage information
                $tokenUsage = [];
                if ($llmResponse->getPromptTokens() !== null) {
                    $tokenUsage['prompt_tokens'] = $llmResponse->getPromptTokens();
                }
                if ($llmResponse->getCompletionTokens() !== null) {
                    $tokenUsage['completion_tokens'] = $llmResponse->getCompletionTokens();
                }
                if ($llmResponse->getTotalTokens() !== null) {
                    $tokenUsage['total_tokens'] = $llmResponse->getTotalTokens();
                }
                
                // Create the response
                $response = $this->createAgentResponse(
                    $task,
                    $llmResponse->getContent(),
                    $trace,
                    microtime(true) - $startTime,
                    true,
                    null,
                    $tokenUsage,
                    $llmResponse->getMetadata()
                );
                
                return $response;
            } catch (\Throwable $e) {
                if ($this->verboseLogging) {
                    error_log("Error during chat completion: " . $e->getMessage());
                }
                
                return $this->createAgentResponse(
                    $task,
                    "An error occurred: " . $e->getMessage(),
                    $trace,
                    microtime(true) - $startTime,
                    false,
                    $e->getMessage()
                );
            }
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
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getGoal(): string
    {
        return $this->goal;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getBackstory(): string
    {
        return $this->backstory;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function addTool(ToolInterface $tool): self
    {
        $this->tools[] = $tool;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withLLM(LLMInterface $llm): self
    {
        $this->llm = $llm;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withMemory(MemoryInterface $memory): self
    {
        $this->memory = $memory;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function withVerboseLogging(bool $verbose = true): self
    {
        $this->verboseLogging = $verbose;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
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
     * Get the LLM used by this agent.
     */
    public function getLLM(): ?LLMInterface
    {
        return $this->llm;
    }

    /**
     * Get the memory system used by this agent.
     */
    public function getMemory(): MemoryInterface
    {
        return $this->memory;
    }

    /**
     * Check if verbose logging is enabled.
     */
    public function isVerboseLoggingEnabled(): bool
    {
        return $this->verboseLogging;
    }

    /**
     * Check if task delegation is allowed.
     */
    public function isDelegationAllowed(): bool
    {
        return $this->allowDelegation;
    }

    /**
     * Get the maximum number of iterations.
     */
    public function getMaxIterations(): int
    {
        return $this->maxIterations;
    }

    /**
     * Enable colorized console output for agent responses.
     */
    public function withColorizedOutput(bool $useColorizedOutput = true): self
    {
        $this->useColorizedOutput = $useColorizedOutput;
        return $this;
    }

    /**
     * Check if colorized output is enabled.
     */
    public function usesColorizedOutput(): bool
    {
        return $this->useColorizedOutput;
    }

    /**
     * Create an appropriate AgentResponse instance based on settings.
     *
     * @param string $task The task given to the agent
     * @param string $finalAnswer The final answer from the agent
     * @param array<mixed> $trace The execution trace
     * @param float $executionTime The execution time in seconds
     * @param bool $successful Whether the task was completed successfully
     * @param string|null $error Any error that occurred
     * @param array<string, int> $tokenUsage Token usage information
     * @param array<string, mixed> $metadata Additional metadata
     * @return AgentResponseInterface The agent response
     */
    private function createAgentResponse(
        string $task,
        string $finalAnswer,
        array $trace = [],
        float $executionTime = 0.0,
        bool $successful = true,
        ?string $error = null,
        array $tokenUsage = [],
        array $metadata = []
    ): AgentResponseInterface {
        if ($this->useColorizedOutput) {
            $response = new ColorizedAgentResponse(
                $task,
                $finalAnswer,
                $trace,
                $executionTime,
                $successful,
                $error,
                $tokenUsage,
                $metadata
            );
            
            return $response;
        }
        
        return new AgentResponse(
            $task,
            $finalAnswer,
            $trace,
            $executionTime,
            $successful,
            $error,
            $tokenUsage,
            $metadata
        );
    }
    
    /**
     * Execute a tool and add to trace.
     *
     * @param ToolInterface $tool The tool to execute
     * @param mixed $input The input for the tool
     * @param array $trace The trace to add the tool execution to
     * @return mixed The tool output
     */
    protected function executeTool(ToolInterface $tool, mixed $input, array &$trace): mixed
    {
        try {
            // Display tool usage start if colorized output is enabled
            if ($this->useColorizedOutput) {
                echo ConsoleOutput::colorize("\nExecuting tool: ", ConsoleOutput::THEME_AGENT_TOOL) . 
                     ConsoleOutput::colorize($tool->getName(), ConsoleOutput::FG_BOLD_YELLOW) . "\n";
            }
            
            // Execute the tool
            $output = $tool->run($input);
            
            // Add tool execution to trace
            $traceItem = [
                'type' => 'tool',
                'tool_name' => $tool->getName(),
                'input' => $input,
                'output' => $output,
                'timestamp' => microtime(true)
            ];
            
            $trace[] = $traceItem;
            
            // Display colorized tool usage if enabled
            if ($this->useColorizedOutput) {
                echo ConsoleOutput::formatToolUsage(
                    $tool->getName(),
                    $input,
                    $output,
                    true
                );
            }
            
            return $output;
        } catch (\Throwable $e) {
            // Add error to trace
            $traceItem = [
                'type' => 'tool',
                'tool_name' => $tool->getName(),
                'input' => $input,
                'error' => $e->getMessage(),
                'timestamp' => microtime(true)
            ];
            
            $trace[] = $traceItem;
            
            // Display colorized error if enabled
            if ($this->useColorizedOutput) {
                echo ConsoleOutput::formatToolUsage(
                    $tool->getName(),
                    $input,
                    $e->getMessage(),
                    false
                );
            }
            
            if ($this->verboseLogging) {
                error_log("Error executing tool {$tool->getName()}: " . $e->getMessage());
            }
            
            throw $e;
        }
    }
}
