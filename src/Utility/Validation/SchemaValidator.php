<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Validation;

use PhpSwarm\Contract\Utility\ValidationResult;

/**
 * Schema validator that provides JSON Schema-like validation.
 */
class SchemaValidator
{
    /**
     * Validate data against a JSON Schema-like structure.
     *
     * @param mixed $data The data to validate
     * @param array<string, mixed> $schema The schema definition
     * @param string $path The current path in the data (for nested validation)
     * @return ValidationResult
     */
    public function validateSchema(mixed $data, array $schema, string $path = ''): ValidationResult
    {
        $errors = [];

        // Type validation
        if (isset($schema['type'])) {
            $typeError = $this->validateSchemaType($data, $schema['type'], $path);
            if ($typeError !== null) {
                $errors[$path ?: '_root'][] = $typeError;
                return ValidationResult::failure($errors);
            }
        }

        // Object validation
        if (is_array($data) && isset($schema['properties'])) {
            $objectErrors = $this->validateObject($data, $schema, $path);
            if ($objectErrors !== []) {
                $errors = array_merge($errors, $objectErrors);
            }
        }

        // Array validation
        if (is_array($data) && isset($schema['items'])) {
            $arrayErrors = $this->validateArray($data, $schema, $path);
            if ($arrayErrors !== []) {
                $errors = array_merge($errors, $arrayErrors);
            }
        }

        // String validation
        if (is_string($data)) {
            $stringErrors = $this->validateString($data, $schema, $path);
            if ($stringErrors !== []) {
                $errors = array_merge($errors, $stringErrors);
            }
        }

        // Number validation
        if (is_numeric($data)) {
            $numberErrors = $this->validateNumber($data, $schema, $path);
            if ($numberErrors !== []) {
                $errors = array_merge($errors, $numberErrors);
            }
        }

        // Enum validation
        if (isset($schema['enum'])) {
            if (!in_array($data, $schema['enum'], true)) {
                $allowedValues = implode(', ', $schema['enum']);
                $errors[$path ?: '_root'][] = "Value must be one of: {$allowedValues}";
            }
        }

        // Const validation
        if (isset($schema['const'])) {
            if ($data !== $schema['const']) {
                $errors[$path ?: '_root'][] = "Value must be exactly: " . json_encode($schema['const']);
            }
        }

        if ($errors !== []) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success(is_array($data) ? $data : []);
    }

    /**
     * Validate type according to JSON Schema types.
     *
     * @param mixed $data The data to validate
     * @param string|array<string> $type The expected type(s)
     * @param string $path The current path
     * @return string|null Error message or null if valid
     */
    private function validateSchemaType(mixed $data, string|array $type, string $path): ?string
    {
        $types = is_array($type) ? $type : [$type];

        foreach ($types as $expectedType) {
            $isValid = match ($expectedType) {
                'null' => is_null($data),
                'boolean' => is_bool($data),
                'object' => is_array($data) && array_is_list($data) === false,
                'array' => is_array($data) && array_is_list($data),
                'number' => is_int($data) || is_float($data),
                'integer' => is_int($data),
                'string' => is_string($data),
                default => false,
            };

            if ($isValid) {
                return null;
            }
        }

        $typeList = implode(' or ', $types);
        return "Expected type {$typeList}, got " . gettype($data);
    }

    /**
     * Validate object properties.
     *
     * @param array<string, mixed> $data The data to validate
     * @param array<string, mixed> $schema The schema definition
     * @param string $path The current path
     * @return array<string, array<string>> Validation errors
     */
    private function validateObject(array $data, array $schema, string $path): array
    {
        $errors = [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        $additionalProperties = $schema['additionalProperties'] ?? true;

        // Check required properties
        foreach ($required as $requiredProperty) {
            if (!array_key_exists($requiredProperty, $data)) {
                $fieldPath = $path ? "{$path}.{$requiredProperty}" : $requiredProperty;
                $errors[$fieldPath][] = "Required property is missing";
            }
        }

        // Validate existing properties
        foreach ($data as $property => $value) {
            $fieldPath = $path ? "{$path}.{$property}" : $property;

            if (isset($properties[$property])) {
                // Validate against property schema
                $result = $this->validateSchema($value, $properties[$property], $fieldPath);
                if ($result->isInvalid()) {
                    $errors = array_merge($errors, $result->getErrors());
                }
            } elseif ($additionalProperties === false) {
                $errors[$fieldPath][] = "Additional property not allowed";
            } elseif (is_array($additionalProperties)) {
                // Validate against additional properties schema
                $result = $this->validateSchema($value, $additionalProperties, $fieldPath);
                if ($result->isInvalid()) {
                    $errors = array_merge($errors, $result->getErrors());
                }
            }
        }

        return $errors;
    }

    /**
     * Validate array items.
     *
     * @param array<mixed> $data The array to validate
     * @param array<string, mixed> $schema The schema definition
     * @param string $path The current path
     * @return array<string, array<string>> Validation errors
     */
    private function validateArray(array $data, array $schema, string $path): array
    {
        $errors = [];
        $items = $schema['items'] ?? [];

        // Validate array length
        if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
            $errors[$path ?: '_root'][] = "Array must have at least {$schema['minItems']} items";
        }

        if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
            $errors[$path ?: '_root'][] = "Array must have at most {$schema['maxItems']} items";
        }

        // Validate uniqueness
        if (isset($schema['uniqueItems']) && $schema['uniqueItems'] === true) {
            if (count($data) !== count(array_unique($data, SORT_REGULAR))) {
                $errors[$path ?: '_root'][] = "Array items must be unique";
            }
        }

        // Validate each item
        if ($items !== []) {
            foreach ($data as $index => $item) {
                $itemPath = $path ? "{$path}[{$index}]" : "[{$index}]";
                $result = $this->validateSchema($item, $items, $itemPath);
                if ($result->isInvalid()) {
                    $errors = array_merge($errors, $result->getErrors());
                }
            }
        }

        return $errors;
    }

    /**
     * Validate string constraints.
     *
     * @param string $data The string to validate
     * @param array<string, mixed> $schema The schema definition
     * @param string $path The current path
     * @return array<string, array<string>> Validation errors
     */
    private function validateString(string $data, array $schema, string $path): array
    {
        $errors = [];
        $fieldPath = $path ?: '_root';

        // Length validation
        if (isset($schema['minLength']) && strlen($data) < $schema['minLength']) {
            $errors[$fieldPath][] = "String must be at least {$schema['minLength']} characters long";
        }

        if (isset($schema['maxLength']) && strlen($data) > $schema['maxLength']) {
            $errors[$fieldPath][] = "String must be at most {$schema['maxLength']} characters long";
        }

        // Pattern validation
        if (isset($schema['pattern']) && !preg_match($schema['pattern'], $data)) {
            $errors[$fieldPath][] = "String does not match the required pattern";
        }

        // Format validation
        if (isset($schema['format'])) {
            $formatError = $this->validateFormat($data, $schema['format']);
            if ($formatError !== null) {
                $errors[$fieldPath][] = $formatError;
            }
        }

        return $errors;
    }

    /**
     * Validate number constraints.
     *
     * @param int|float $data The number to validate
     * @param array<string, mixed> $schema The schema definition
     * @param string $path The current path
     * @return array<string, array<string>> Validation errors
     */
    private function validateNumber(int|float $data, array $schema, string $path): array
    {
        $errors = [];
        $fieldPath = $path ?: '_root';

        // Range validation
        if (isset($schema['minimum']) && $data < $schema['minimum']) {
            $errors[$fieldPath][] = "Number must be at least {$schema['minimum']}";
        }

        if (isset($schema['maximum']) && $data > $schema['maximum']) {
            $errors[$fieldPath][] = "Number must be at most {$schema['maximum']}";
        }

        if (isset($schema['exclusiveMinimum']) && $data <= $schema['exclusiveMinimum']) {
            $errors[$fieldPath][] = "Number must be greater than {$schema['exclusiveMinimum']}";
        }

        if (isset($schema['exclusiveMaximum']) && $data >= $schema['exclusiveMaximum']) {
            $errors[$fieldPath][] = "Number must be less than {$schema['exclusiveMaximum']}";
        }

        // Multiple validation
        if (isset($schema['multipleOf']) && $schema['multipleOf'] > 0) {
            if (fmod($data, $schema['multipleOf']) !== 0.0) {
                $errors[$fieldPath][] = "Number must be a multiple of {$schema['multipleOf']}";
            }
        }

        return $errors;
    }

    /**
     * Validate string formats.
     *
     * @param string $data The string to validate
     * @param string $format The format name
     * @return string|null Error message or null if valid
     */
    private function validateFormat(string $data, string $format): ?string
    {
        return match ($format) {
            'email' => filter_var($data, FILTER_VALIDATE_EMAIL) === false ? 'Invalid email format' : null,
            'uri', 'url' => filter_var($data, FILTER_VALIDATE_URL) === false ? 'Invalid URL format' : null,
            'ipv4' => filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false ? 'Invalid IPv4 format' : null,
            'ipv6' => filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false ? 'Invalid IPv6 format' : null,
            'date' => $this->validateDate($data, 'Y-m-d') ? null : 'Invalid date format (YYYY-MM-DD expected)',
            'time' => $this->validateDate($data, 'H:i:s') ? null : 'Invalid time format (HH:MM:SS expected)',
            'date-time' => $this->validateDateTime($data) ? null : 'Invalid date-time format (ISO 8601 expected)',
            'uuid' => $this->validateUuid($data) ? null : 'Invalid UUID format',
            'hostname' => $this->validateHostname($data) ? null : 'Invalid hostname format',
            default => null, // Unknown format, skip validation
        };
    }

    /**
     * Validate date format.
     */
    private function validateDate(string $data, string $format): bool
    {
        $date = \DateTime::createFromFormat($format, $data);
        return $date !== false && $date->format($format) === $data;
    }

    /**
     * Validate ISO 8601 date-time format.
     */
    private function validateDateTime(string $data): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/i', $data);
    }

    /**
     * Validate UUID format.
     */
    private function validateUuid(string $data): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $data);
    }

    /**
     * Validate hostname format.
     */
    private function validateHostname(string $data): bool
    {
        return (bool) preg_match('/^(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])(?:\.(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]))*$/', $data);
    }
}
