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
        $trace = [];

        // Build the system prompt based on agent configuration
        $systemPrompt = "You are {$this->name}, a {$this->role}.\n";
        $systemPrompt .= "Your goal is: {$this->goal}\n";
        
        if (!empty($this->backstory)) {
            $systemPrompt .= "Backstory: {$this->backstory}\n";
        }
        
        if (!empty($this->tools)) {
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
                    function($chunk) use (&$finalContent, $context) {
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
                $response = new AgentResponse(
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
                
                return new AgentResponse(
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
                $response = new AgentResponse(
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
                
                return new AgentResponse(
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
