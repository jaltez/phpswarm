<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Prompt;

/**
 * Interface for managing prompts.
 *
 * A prompt manager provides a way to register, retrieve, and manage
 * prompts and prompt templates.
 */
interface PromptManagerInterface
{
    /**
     * Register a prompt.
     *
     * @param PromptInterface $prompt The prompt to register
     * @return self Fluent interface
     */
    public function registerPrompt(PromptInterface $prompt): self;

    /**
     * Register a prompt template.
     *
     * @param PromptTemplateInterface $template The prompt template to register
     * @return self Fluent interface
     */
    public function registerTemplate(PromptTemplateInterface $template): self;

    /**
     * Get a prompt by name.
     *
     * @param string $name The name of the prompt
     * @return PromptInterface|null The prompt or null if not found
     */
    public function getPrompt(string $name): ?PromptInterface;

    /**
     * Get a template by name.
     *
     * @param string $name The name of the template
     * @return PromptTemplateInterface|null The template or null if not found
     */
    public function getTemplate(string $name): ?PromptTemplateInterface;

    /**
     * Check if a prompt exists.
     *
     * @param string $name The name of the prompt
     * @return bool True if the prompt exists
     */
    public function hasPrompt(string $name): bool;

    /**
     * Check if a template exists.
     *
     * @param string $name The name of the template
     * @return bool True if the template exists
     */
    public function hasTemplate(string $name): bool;

    /**
     * Get all registered prompts.
     *
     * @return array<string, PromptInterface> The registered prompts
     */
    public function getPrompts(): array;

    /**
     * Get all registered templates.
     *
     * @return array<string, PromptTemplateInterface> The registered templates
     */
    public function getTemplates(): array;

    /**
     * Remove a prompt.
     *
     * @param string $name The name of the prompt to remove
     * @return bool True if the prompt was removed, false if it didn't exist
     */
    public function removePrompt(string $name): bool;

    /**
     * Remove a template.
     *
     * @param string $name The name of the template to remove
     * @return bool True if the template was removed, false if it didn't exist
     */
    public function removeTemplate(string $name): bool;
} 