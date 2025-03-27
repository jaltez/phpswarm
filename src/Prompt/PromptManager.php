<?php

declare(strict_types=1);

namespace PhpSwarm\Prompt;

use PhpSwarm\Contract\Prompt\PromptInterface;
use PhpSwarm\Contract\Prompt\PromptManagerInterface;
use PhpSwarm\Contract\Prompt\PromptTemplateInterface;

/**
 * Manager for prompts and prompt templates.
 */
class PromptManager implements PromptManagerInterface
{
    /**
     * @var array<string, PromptInterface> Registered prompts
     */
    protected array $prompts = [];

    /**
     * @var array<string, PromptTemplateInterface> Registered templates
     */
    protected array $templates = [];

    /**
     * Create a new PromptManager instance.
     */
    public function __construct()
    {
        // Initialize with standard templates
        $this->registerStandardTemplates();
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function registerPrompt(PromptInterface $prompt): self
    {
        $this->prompts[$prompt->getName()] = $prompt;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function registerTemplate(PromptTemplateInterface $template): self
    {
        $this->templates[$template->getName()] = $template;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getPrompt(string $name): ?PromptInterface
    {
        return $this->prompts[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTemplate(string $name): ?PromptTemplateInterface
    {
        return $this->templates[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function hasPrompt(string $name): bool
    {
        return isset($this->prompts[$name]);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function hasTemplate(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getPrompts(): array
    {
        return $this->prompts;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function removePrompt(string $name): bool
    {
        if (isset($this->prompts[$name])) {
            unset($this->prompts[$name]);
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function removeTemplate(string $name): bool
    {
        if (isset($this->templates[$name])) {
            unset($this->templates[$name]);
            return true;
        }

        return false;
    }

    /**
     * Register standard templates.
     */
    protected function registerStandardTemplates(): void
    {
        // Agent task template
        $agentTaskTemplate = new PromptTemplate(
            'agent_task',
            'Template for agent tasks',
            "You are an AI assistant with the role of {{role}}.\n\n" .
            "Your goal is: {{goal}}\n\n" .
            "Your current task is: {{task}}\n\n" .
            "{{context}}\n\n" .
            "Please complete this task to the best of your abilities."
        );
        $this->registerTemplate($agentTaskTemplate);

        // Chat conversation template
        $chatTemplate = new PromptTemplate(
            'chat_conversation',
            'Template for chat conversations',
            "Below is a conversation between a human and an AI assistant.\n\n" .
            "{{conversation_history}}\n\n" .
            "Human: {{user_input}}\n" .
            "AI: "
        );
        $this->registerTemplate($chatTemplate);

        // Tool use template
        $toolUseTemplate = new PromptTemplate(
            'tool_use',
            'Template for using tools',
            "You have access to the following tools:\n\n" .
            "{{tools_description}}\n\n" .
            "To use a tool, respond with:\n" .
            "```json\n" .
            "{\n" .
            "  \"tool\": \"tool_name\",\n" .
            "  \"parameters\": {\n" .
            "    \"param1\": \"value1\",\n" .
            "    \"param2\": \"value2\"\n" .
            "  }\n" .
            "}\n" .
            "```\n\n" .
            "Your task is: {{task}}\n\n" .
            "{{context}}"
        );
        $this->registerTemplate($toolUseTemplate);

        // Summary template
        $summaryTemplate = new PromptTemplate(
            'summary',
            'Template for summarizing content',
            "Please summarize the following content:\n\n" .
            "{{content}}\n\n" .
            "Summary length: {{length}}"
        );
        $this->registerTemplate($summaryTemplate);
    }

    /**
     * Create a prompt from a template.
     *
     * @param string $templateName The name of the template to use
     * @param string $promptName The name of the prompt to create
     * @param string $promptDescription The description of the prompt
     * @param array<string, mixed> $variables Variables to pre-populate
     * @return PromptInterface|null The created prompt or null if template not found
     */
    public function createPromptFromTemplate(
        string $templateName,
        string $promptName,
        string $promptDescription,
        array $variables = []
    ): ?PromptInterface {
        $template = $this->getTemplate($templateName);
        
        if ($template === null) {
            return null;
        }
        
        $prompt = $template->createPrompt($promptName, $promptDescription, $variables);
        $this->registerPrompt($prompt);
        
        return $prompt;
    }
} 