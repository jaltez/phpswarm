<?php

declare(strict_types=1);

namespace PhpSwarm\Tool;

use PhpSwarm\Contract\Tool\ToolInterface;
use PhpSwarm\Exception\Tool\ToolExecutionException;

/**
 * Base class for tools that provides common functionality.
 */
abstract class BaseTool implements ToolInterface
{
    /**
     * @var array<string, array<string, mixed>> The parameters schema
     */
    protected array $parametersSchema = [];

    /**
     * @var array<string> The tags associated with the tool
     */
    protected array $tags = [];

    /**
     * @var bool Whether the tool requires authentication
     */
    protected bool $requiresAuth = false;

    /**
     * Create a new BaseTool instance.
     *
     * @param string $name The name of the tool
     * @param string $description The description of the tool
     */
    public function __construct(protected string $name, protected string $description)
    {
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
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getParametersSchema(): array
    {
        return $this->parametersSchema;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function requiresAuthentication(): bool
    {
        return false;
    }

    /**
     * Add a tag to the tool.
     *
     * @param string $tag The tag to add
     */
    #[\Override]
    public function addTag(string $tag): self
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    /**
     * Validate the parameters against the schema.
     *
     * @param array<string, mixed> $parameters The parameters to validate
     * @throws ToolExecutionException If validation fails
     */
    protected function validateParameters(array $parameters): void
    {
        foreach ($this->parametersSchema as $name => $schema) {
            $required = $schema['required'] ?? false;

            if ($required && !isset($parameters[$name])) {
                throw new ToolExecutionException("Missing required parameter: {$name}");
            }

            if (!isset($parameters[$name])) {
                continue;
            }

            $value = $parameters[$name];
            $type = $schema['type'] ?? null;

            if ($type && !$this->validateType($value, $type)) {
                throw new ToolExecutionException(
                    "Invalid type for parameter {$name}: expected {$type}, got " . gettype($value)
                );
            }

            if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
                throw new ToolExecutionException(
                    "Invalid value for parameter {$name}: must be one of [" . implode(', ', $schema['enum']) . "]"
                );
            }
        }
    }

    /**
     * Validate the type of a value.
     *
     * @param mixed $value The value to validate
     * @param string $type The expected type
     * @return bool True if the value is of the expected type
     */
    private function validateType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer', 'int' => is_int($value),
            'number', 'float' => is_float($value) || is_int($value),
            'boolean', 'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => is_null($value),
            'mixed' => true,
            default => false,
        };
    }

    /**
     * Set whether the tool requires authentication.
     */
    public function setRequiresAuthentication(bool $requiresAuth): self
    {
        $this->requiresAuth = $requiresAuth;
        return $this;
    }
}
