<?php

declare(strict_types=1);

namespace PhpSwarm\Exception\Tool;

use PhpSwarm\Exception\PhpSwarmException;

/**
 * Exception thrown when there is an error executing a tool.
 */
class ToolExecutionException extends PhpSwarmException
{
    /**
     * @var string|null The name of the tool that caused the exception
     */
    private ?string $toolName;
    
    /**
     * Create a new ToolExecutionException.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous throwable used for the exception chaining
     * @param string|null $toolName The name of the tool that caused the exception
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $toolName = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->toolName = $toolName;
    }
    
    /**
     * Get the name of the tool that caused the exception.
     *
     * @return string|null The tool name
     */
    public function getToolName(): ?string
    {
        return $this->toolName;
    }
    
    /**
     * Set the name of the tool that caused the exception.
     *
     * @param string $toolName The tool name
     * @return self
     */
    public function setToolName(string $toolName): self
    {
        $this->toolName = $toolName;
        
        return $this;
    }
} 