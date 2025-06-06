<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Validation;

use PhpSwarm\Contract\Utility\ValidatorInterface;
use PhpSwarm\Contract\Utility\ValidationResult;
use PhpSwarm\Exception\Utility\ValidationException;
use PhpSwarm\Utility\Validation\Attribute\InArray;
use PhpSwarm\Utility\Validation\Attribute\Length;
use PhpSwarm\Utility\Validation\Attribute\Pattern;
use PhpSwarm\Utility\Validation\Attribute\Range;
use PhpSwarm\Utility\Validation\Attribute\Required;
use PhpSwarm\Utility\Validation\Attribute\Type;
use ReflectionClass;
use ReflectionProperty;

/**
 * Main validator implementation with schema and attribute-based validation.
 */
class Validator implements ValidatorInterface
{
    /**
     * @var array<string, callable> Custom validation rules
     */
    private array $customRules = [];

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $data, array $schema): ValidationResult
    {
        $errors = [];
        $validatedData = [];

        if (!is_array($data)) {
            $errors['_root'] = ['Data must be an array'];
            return ValidationResult::failure($errors);
        }

        foreach ($schema as $field => $rules) {
            $value = $data[$field] ?? null;
            $fieldErrors = $this->validateField($field, $value, $rules, isset($data[$field]));

            if ($fieldErrors !== []) {
                $errors[$field] = $fieldErrors;
            } else {
                $validatedData[$field] = $this->transformValue($value, $rules);
            }
        }

        if ($errors !== []) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success($validatedData);
    }

    /**
     * {@inheritdoc}
     */
    public function validateOrThrow(mixed $data, array $schema): void
    {
        $result = $this->validate($data, $schema);
        if ($result->isInvalid()) {
            throw ValidationException::fromResult($result);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateObject(object $object): ValidationResult
    {
        $reflection = new ReflectionClass($object);
        $errors = [];
        $validatedData = [];

        foreach ($reflection->getProperties() as $property) {
            $fieldName = $property->getName();
            $property->setAccessible(true);
            $value = $property->getValue($object);
            $isSet = $property->isInitialized($object);

            $fieldErrors = $this->validateProperty($property, $value, $isSet);

            if ($fieldErrors !== []) {
                $errors[$fieldName] = $fieldErrors;
            } else {
                $validatedData[$fieldName] = $value;
            }
        }

        if ($errors !== []) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success($validatedData);
    }

    /**
     * {@inheritdoc}
     */
    public function addRule(string $name, callable $validator): void
    {
        $this->customRules[$name] = $validator;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRule(string $name): bool
    {
        return isset($this->customRules[$name]);
    }

    /**
     * Validate a single field against its rules.
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $rules Validation rules
     * @param bool $isSet Whether the field is set in the data
     * @return array<string> Array of error messages
     */
    private function validateField(string $field, mixed $value, array $rules, bool $isSet): array
    {
        $errors = [];

        // Check required
        if (($rules['required'] ?? false) && (!$isSet || $value === null || $value === '')) {
            $errors[] = "The {$field} field is required.";
            return $errors; // Don't continue validation if required field is missing
        }

        // Skip other validations if field is not set and not required
        if (!$isSet && !($rules['required'] ?? false)) {
            return $errors;
        }

        // Type validation
        if (isset($rules['type'])) {
            $typeError = $this->validateType($value, $rules['type']);
            if ($typeError !== null) {
                $errors[] = "The {$field} field must be of type {$rules['type']}.";
            }
        }

        // Length validation
        if (isset($rules['min_length']) || isset($rules['max_length']) || isset($rules['length'])) {
            $lengthError = $this->validateLength($value, $rules);
            if ($lengthError !== null) {
                $errors[] = "The {$field} field {$lengthError}.";
            }
        }

        // Range validation
        if (isset($rules['min']) || isset($rules['max'])) {
            $rangeError = $this->validateRange($value, $rules);
            if ($rangeError !== null) {
                $errors[] = "The {$field} field {$rangeError}.";
            }
        }

        // Pattern validation
        if (isset($rules['pattern'])) {
            $patternError = $this->validatePattern($value, $rules['pattern']);
            if ($patternError !== null) {
                $errors[] = "The {$field} field {$patternError}.";
            }
        }

        // Enum validation
        if (isset($rules['enum']) && is_array($rules['enum'])) {
            if (!in_array($value, $rules['enum'], true)) {
                $allowedValues = implode(', ', $rules['enum']);
                $errors[] = "The {$field} field must be one of: {$allowedValues}.";
            }
        }

        // Custom rule validation
        if (isset($rules['rules']) && is_array($rules['rules'])) {
            foreach ($rules['rules'] as $ruleName) {
                if (isset($this->customRules[$ruleName])) {
                    $customError = ($this->customRules[$ruleName])($value, $field);
                    if ($customError !== null) {
                        $errors[] = $customError;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a property using its attributes.
     *
     * @param ReflectionProperty $property The property reflection
     * @param mixed $value The property value
     * @param bool $isSet Whether the property is set
     * @return array<string> Array of error messages
     */
    private function validateProperty(ReflectionProperty $property, mixed $value, bool $isSet): array
    {
        $errors = [];
        $fieldName = $property->getName();

        foreach ($property->getAttributes() as $attribute) {
            $attributeInstance = $attribute->newInstance();

            if ($attributeInstance instanceof Required) {
                if (!$isSet || $value === null || $value === '') {
                    $message = $attributeInstance->message ?? "The {$fieldName} field is required.";
                    $errors[] = $message;
                    return $errors; // Don't continue if required field is missing
                }
            }

            if (!$isSet) {
                continue; // Skip other validations if field is not set
            }

            if ($attributeInstance instanceof Type) {
                $typeError = $this->validateType($value, $attributeInstance->type);
                if ($typeError !== null) {
                    $message = $attributeInstance->message ?? "The {$fieldName} field must be of type {$attributeInstance->type}.";
                    $errors[] = $message;
                }
            }

            if ($attributeInstance instanceof Length) {
                $lengthRules = [
                    'min_length' => $attributeInstance->min,
                    'max_length' => $attributeInstance->max,
                    'length' => $attributeInstance->exact,
                ];
                $lengthError = $this->validateLength($value, $lengthRules);
                if ($lengthError !== null) {
                    $message = $attributeInstance->message ?? "The {$fieldName} field {$lengthError}.";
                    $errors[] = $message;
                }
            }

            if ($attributeInstance instanceof Range) {
                $rangeRules = [
                    'min' => $attributeInstance->min,
                    'max' => $attributeInstance->max,
                ];
                $rangeError = $this->validateRange($value, $rangeRules);
                if ($rangeError !== null) {
                    $message = $attributeInstance->message ?? "The {$fieldName} field {$rangeError}.";
                    $errors[] = $message;
                }
            }

            if ($attributeInstance instanceof Pattern) {
                $patternError = $this->validatePattern($value, $attributeInstance->pattern);
                if ($patternError !== null) {
                    $message = $attributeInstance->message ?? "The {$fieldName} field {$patternError}.";
                    $errors[] = $message;
                }
            }

            if ($attributeInstance instanceof InArray) {
                if (!in_array($value, $attributeInstance->values, $attributeInstance->strict)) {
                    $allowedValues = implode(', ', $attributeInstance->values);
                    $message = $attributeInstance->message ?? "The {$fieldName} field must be one of: {$allowedValues}.";
                    $errors[] = $message;
                }
            }
        }

        return $errors;
    }

    /**
     * Validate the type of a value.
     *
     * @param mixed $value The value to validate
     * @param string $expectedType The expected type
     * @return string|null Error message or null if valid
     */
    private function validateType(mixed $value, string $expectedType): ?string
    {
        $isValid = match ($expectedType) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double', 'number' => is_float($value) || is_int($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => is_null($value),
            'mixed' => true,
            default => false,
        };

        return $isValid ? null : "invalid type";
    }

    /**
     * Validate the length of a string or array.
     *
     * @param mixed $value The value to validate
     * @param array<string, mixed> $rules Length rules
     * @return string|null Error message or null if valid
     */
    private function validateLength(mixed $value, array $rules): ?string
    {
        if (!is_string($value) && !is_array($value)) {
            return "must be string or array for length validation";
        }

        $length = is_string($value) ? strlen($value) : count($value);

        if (isset($rules['length']) && $length !== $rules['length']) {
            return "must be exactly {$rules['length']} characters long";
        }

        if (isset($rules['min_length']) && $length < $rules['min_length']) {
            return "must be at least {$rules['min_length']} characters long";
        }

        if (isset($rules['max_length']) && $length > $rules['max_length']) {
            return "must not exceed {$rules['max_length']} characters";
        }

        return null;
    }

    /**
     * Validate numeric range.
     *
     * @param mixed $value The value to validate
     * @param array<string, mixed> $rules Range rules
     * @return string|null Error message or null if valid
     */
    private function validateRange(mixed $value, array $rules): ?string
    {
        if (!is_numeric($value)) {
            return "must be numeric for range validation";
        }

        $numValue = (float) $value;

        if (isset($rules['min']) && $numValue < $rules['min']) {
            return "must be at least {$rules['min']}";
        }

        if (isset($rules['max']) && $numValue > $rules['max']) {
            return "must not exceed {$rules['max']}";
        }

        return null;
    }

    /**
     * Validate against a regex pattern.
     *
     * @param mixed $value The value to validate
     * @param string $pattern The regex pattern
     * @return string|null Error message or null if valid
     */
    private function validatePattern(mixed $value, string $pattern): ?string
    {
        if (!is_string($value)) {
            return "must be string for pattern validation";
        }

        if (!preg_match($pattern, $value)) {
            return "format is invalid";
        }

        return null;
    }

    /**
     * Transform value based on rules (e.g., type casting).
     *
     * @param mixed $value The value to transform
     * @param array<string, mixed> $rules Transformation rules
     * @return mixed The transformed value
     */
    private function transformValue(mixed $value, array $rules): mixed
    {
        // Apply default value if value is null
        if ($value === null && isset($rules['default'])) {
            return $rules['default'];
        }

        // Type casting
        if (isset($rules['type']) && $value !== null) {
            return match ($rules['type']) {
                'int', 'integer' => (int) $value,
                'float', 'double' => (float) $value,
                'bool', 'boolean' => (bool) $value,
                'string' => (string) $value,
                'array' => is_array($value) ? $value : [$value],
                default => $value,
            };
        }

        return $value;
    }
}
