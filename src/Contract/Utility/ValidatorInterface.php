<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Utility;

use PhpSwarm\Exception\Utility\ValidationException;

/**
 * Interface for data validation components.
 *
 * Provides methods for validating data against schemas, rules, and attributes.
 */
interface ValidatorInterface
{
    /**
     * Validate data against a schema.
     *
     * @param mixed $data The data to validate
     * @param array<string, mixed> $schema The validation schema
     * @return ValidationResult The validation result
     */
    public function validate(mixed $data, array $schema): ValidationResult;

    /**
     * Validate data and throw exception on failure.
     *
     * @param mixed $data The data to validate
     * @param array<string, mixed> $schema The validation schema
     * @throws ValidationException If validation fails
     */
    public function validateOrThrow(mixed $data, array $schema): void;

    /**
     * Validate an object using its validation attributes.
     *
     * @param object $object The object to validate
     * @return ValidationResult The validation result
     */
    public function validateObject(object $object): ValidationResult;

    /**
     * Add custom validation rule.
     *
     * @param string $name The rule name
     * @param callable $validator The validator function
     */
    public function addRule(string $name, callable $validator): void;

    /**
     * Check if a rule exists.
     *
     * @param string $name The rule name
     */
    public function hasRule(string $name): bool;
}
