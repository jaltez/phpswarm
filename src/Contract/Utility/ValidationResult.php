<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Utility;

/**
 * Represents the result of a validation operation.
 */
class ValidationResult
{
    /**
     * @param bool $isValid Whether the validation passed
     * @param array<string, array<string>> $errors The validation errors grouped by field
     * @param array<string, mixed> $validatedData The validated and potentially transformed data
     */
    public function __construct(
        private readonly bool $isValid,
        private readonly array $errors = [],
        private readonly array $validatedData = []
    ) {}

    /**
     * Check if the validation passed.
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Check if the validation failed.
     */
    public function isInvalid(): bool
    {
        return !$this->isValid;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     *
     * @param string $field The field name
     * @return array<string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a field has errors.
     *
     * @param string $field The field name
     */
    public function hasFieldErrors(string $field): bool
    {
        return isset($this->errors[$field]) && $this->errors[$field] !== [];
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return array<string>
     */
    public function getAllErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }

    /**
     * Get the first error message.
     */
    public function getFirstError(): ?string
    {
        $all = $this->getAllErrorMessages();
        return $all[0] ?? null;
    }

    /**
     * Get the validated data.
     *
     * @return array<string, mixed>
     */
    public function getValidatedData(): array
    {
        return $this->validatedData;
    }

    /**
     * Get a specific field from validated data.
     *
     * @param string $field The field name
     * @param mixed $default The default value if field doesn't exist
     */
    public function getValidatedField(string $field, mixed $default = null): mixed
    {
        return $this->validatedData[$field] ?? $default;
    }

    /**
     * Create a successful validation result.
     *
     * @param array<string, mixed> $validatedData The validated data
     */
    public static function success(array $validatedData = []): self
    {
        return new self(true, [], $validatedData);
    }

    /**
     * Create a failed validation result.
     *
     * @param array<string, array<string>> $errors The validation errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }
}
