<?php

declare(strict_types=1);

namespace PhpSwarm\Exception\Security;

use PhpSwarm\Exception\PhpSwarmException;

/**
 * Exception thrown when a security-related error occurs.
 */
class SecurityException extends PhpSwarmException
{
    /**
     * Create a new security exception for input validation failure.
     *
     * @param string $input The input that failed validation
     * @param string|null $reason Optional reason for the failure
     * @return self
     */
    public static function invalidInput(string $input, ?string $reason = null): self
    {
        $message = 'Input validation failed';
        if ($reason !== null) {
            $message .= ': ' . $reason;
        }
        
        return new self($message);
    }
    
    /**
     * Create a new security exception for unsafe path access.
     *
     * @param string $path The path that was determined to be unsafe
     * @param string $operation The operation that was attempted (read, write, execute)
     * @param string|null $reason Optional reason for the failure
     * @return self
     */
    public static function unsafePath(string $path, string $operation, ?string $reason = null): self
    {
        $message = sprintf('Unsafe path access: %s (operation: %s)', $path, $operation);
        if ($reason !== null) {
            $message .= ': ' . $reason;
        }
        
        return new self($message);
    }
    
    /**
     * Create a new security exception for prompt injection detection.
     *
     * @param string $prompt The prompt that was flagged for potential injection
     * @param string|null $pattern The pattern that was matched
     * @return self
     */
    public static function promptInjectionDetected(string $prompt, ?string $pattern = null): self
    {
        $message = 'Potential prompt injection detected';
        if ($pattern !== null) {
            $message .= sprintf(' (pattern: %s)', $pattern);
        }
        
        return new self($message);
    }
    
    /**
     * Create a new security exception for unsafe command execution attempt.
     *
     * @param string $command The command that was determined to be unsafe
     * @param string|null $reason Optional reason for the failure
     * @return self
     */
    public static function unsafeCommand(string $command, ?string $reason = null): self
    {
        $message = sprintf('Unsafe command execution attempt: %s', $command);
        if ($reason !== null) {
            $message .= ': ' . $reason;
        }
        
        return new self($message);
    }
    
    /**
     * Create a new security exception for authentication failure.
     *
     * @param string $context The context in which authentication failed
     * @param string|null $reason Optional reason for the failure
     * @return self
     */
    public static function authenticationFailure(string $context, ?string $reason = null): self
    {
        $message = sprintf('Authentication failed for %s', $context);
        if ($reason !== null) {
            $message .= ': ' . $reason;
        }
        
        return new self($message);
    }
    
    /**
     * Create a new security exception for authorization failure.
     *
     * @param string $context The context in which authorization failed
     * @param string|null $reason Optional reason for the failure
     * @return self
     */
    public static function authorizationFailure(string $context, ?string $reason = null): self
    {
        $message = sprintf('Authorization failed for %s', $context);
        if ($reason !== null) {
            $message .= ': ' . $reason;
        }
        
        return new self($message);
    }
} 