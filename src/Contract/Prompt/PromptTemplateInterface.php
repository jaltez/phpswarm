<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Prompt;

/**
 * Interface for prompt templates.
 *
 * A prompt template manages the creation and modification of prompt templates,
 * allowing for reuse of common prompt patterns.
 */
interface PromptTemplateInterface
{
    /**
     * Create a new prompt from this template.
     *
     * @param string $name The name of the prompt
     * @param string $description The description of the prompt
     * @param array<string, mixed> $variables Optional variables to pre-populate
     * @return PromptInterface The created prompt
     */
    public function createPrompt(string $name, string $description, array $variables = []): PromptInterface;

    /**
     * Get the template content.
     */
    public function getContent(): string;

    /**
     * Get the template name.
     */
    public function getName(): string;

    /**
     * Get the template description.
     */
    public function getDescription(): string;

    /**
     * Set the template content.
     *
     * @param string $content The template content
     * @return self Fluent interface
     */
    public function setContent(string $content): self;

    /**
     * Get the available variable placeholders in this template.
     *
     * @return array<string> Array of variable names found in the template
     */
    public function getAvailableVariables(): array;
} 