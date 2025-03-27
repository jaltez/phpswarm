<?php

declare(strict_types=1);

namespace PhpSwarm\Prompt;

use PhpSwarm\Contract\Prompt\PromptInterface;
use PhpSwarm\Exception\Prompt\PromptRenderException;

/**
 * Base class for prompts that provides common functionality.
 */
class BasePrompt implements PromptInterface
{
    /**
     * @var array<string, array{description: string, required: bool}> Variables for the prompt
     */
    protected array $variables = [];

    /**
     * Create a new BasePrompt instance.
     *
     * @param string $name The name of the prompt
     * @param string $description The description of the prompt
     * @param string $template The prompt template
     */
    public function __construct(
        protected string $name,
        protected string $description,
        protected string $template
    ) {
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
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function render(array $variables = []): string
    {
        $result = $this->template;
        $missingVariables = [];

        foreach ($this->variables as $name => $info) {
            $placeholder = "{{" . $name . "}}";
            
            if (isset($variables[$name])) {
                $result = str_replace($placeholder, (string) $variables[$name], $result);
            } elseif ($info['required']) {
                $missingVariables[] = $name;
            }
        }

        if (!empty($missingVariables)) {
            throw new PromptRenderException(
                "Missing required variables: " . implode(', ', $missingVariables)
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function addVariable(string $name, string $description, bool $required = true): self
    {
        $this->variables[$name] = [
            'description' => $description,
            'required' => $required,
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * Set the template content.
     *
     * @param string $template The template content
     * @return self Fluent interface
     */
    public function setTemplate(string $template): self
    {
        $this->template = $template;
        return $this;
    }
} 