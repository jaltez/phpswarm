<?php

declare(strict_types=1);

namespace PhpSwarm\Exception\Utility;

use PhpSwarm\Exception\PhpSwarmException;
use PhpSwarm\Contract\Utility\ValidationResult;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends PhpSwarmException
{
    /**
     * @param string $message The exception message
     * @param ValidationResult|null $result The validation result
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(
        string $message = 'Validation failed',
        private readonly ?ValidationResult $result = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 400, $previous);
    }

    /**
     * Get the validation result.
     */
    public function getValidationResult(): ?ValidationResult
    {
        return $this->result;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getValidationErrors(): array
    {
        return $this->result?->getErrors() ?? [];
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return array<string>
     */
    public function getErrorMessages(): array
    {
        return $this->result?->getAllErrorMessages() ?? [];
    }

    /**
     * Create a ValidationException from a ValidationResult.
     */
    public static function fromResult(ValidationResult $result, string $message = 'Validation failed'): self
    {
        return new self($message, $result);
    }
}
