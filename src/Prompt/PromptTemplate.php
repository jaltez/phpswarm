<?php

declare(strict_types=1);

namespace PhpSwarm\Prompt;

use PhpSwarm\Contract\Prompt\PromptInterface;
use PhpSwarm\Contract\Prompt\PromptTemplateInterface;

/**
 * Implementation of a prompt template.
 */
class PromptTemplate implements PromptTemplateInterface
{
    /**
     * Create a new PromptTemplate instance.
     *
     * @param string $name The name of the template
     * @param string $description The description of the template
     * @param string $content The content of the template
     */
    public function __construct(
        protected string $name,
        protected string $description,
        protected string $content
    ) {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function createPrompt(string $name, string $description, array $variables = []): PromptInterface
    {
        $prompt = new BasePrompt($name, $description, $this->content);

        // Extract variables from the template and add them to the prompt
        $placeholders = $this->getAvailableVariables();
        foreach ($placeholders as $placeholder) {
            $description = $variables[$placeholder]['description'] ?? "Variable: $placeholder";
            $required = $variables[$placeholder]['required'] ?? true;
            $prompt->addVariable($placeholder, $description, $required);
        }

        return $prompt;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getContent(): string
    {
        return $this->content;
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
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getAvailableVariables(): array
    {
        $matches = [];
        $pattern = '/\{\{([a-zA-Z0-9_]+)\}\}/';
        
        preg_match_all($pattern, $this->content, $matches);
        
        return $matches[1] ?? [];
    }
} 