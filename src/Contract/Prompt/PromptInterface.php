<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Prompt;

/**
 * Interface for prompts that agents can use.
 *
 * A prompt is a structured message or template that helps guide
 * an LLM to generate a specific type of response.
 */
interface PromptInterface
{
    /**
     * Get the name of the prompt.
     */
    public function getName(): string;

    /**
     * Get the description of the prompt.
     */
    public function getDescription(): string;

    /**
     * Get the prompt template.
     */
    public function getTemplate(): string;

    /**
     * Render the prompt by replacing variables with their values.
     *
     * @param array<string, mixed> $variables The variables to replace in the template
     * @return string The rendered prompt with variables replaced
     */
    public function render(array $variables = []): string;

    /**
     * Add a variable to the prompt template.
     *
     * @param string $name The name of the variable
     * @param string $description The description of the variable
     * @param bool $required Whether the variable is required
     * @return self Fluent interface
     */
    public function addVariable(string $name, string $description, bool $required = true): self;

    /**
     * Get the variables of the prompt template.
     *
     * @return array<string, array{description: string, required: bool}> The variables
     */
    public function getVariables(): array;
} 