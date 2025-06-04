<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Factory\PhpSwarmFactory;

// Create the factory
$factory = new PhpSwarmFactory();

// Create a prompt manager
$promptManager = $factory->createPromptManager();

// Display pre-registered templates
echo "Pre-registered templates:\n";
echo "========================\n";
foreach ($promptManager->getTemplates() as $template) {
    echo "- {$template->getName()}: {$template->getDescription()}\n";
}
echo "\n";

// Create a custom template
$customTemplate = $factory->createPromptTemplate(
    'custom_template',
    'A custom template for greeting',
    "Hello {{name}}!\n\nWelcome to {{place}}.\n\nToday is {{day}}.",
    ['register_with_manager' => true]
);

// Show available variables in the template
echo "Variables in the custom template:\n";
echo "===============================\n";
foreach ($customTemplate->getAvailableVariables() as $variable) {
    echo "- {$variable}\n";
}
echo "\n";

// Create a prompt from the template
$greetingPrompt = $factory->createPromptFromTemplate(
    'custom_template',
    'greeting_prompt',
    'A greeting prompt for new users',
    [
        'name' => [
            'description' => 'The name of the person to greet',
            'required' => true,
        ],
        'place' => [
            'description' => 'The place to welcome them to',
            'required' => true,
        ],
        'day' => [
            'description' => 'The day of the week',
            'required' => false,
        ],
    ]
);

// Check if prompt was created
if ($greetingPrompt === null) {
    echo "Failed to create prompt from template.\n";
    exit(1);
}

// Render the prompt with variables
echo "Rendered greeting prompt:\n";
echo "=======================\n";
try {
    echo $greetingPrompt->render([
        'name' => 'John',
        'place' => 'PHPSwarm',
        'day' => 'Monday',
    ]);
    echo "\n\n";
} catch (\PhpSwarm\Exception\Prompt\PromptRenderException $e) {
    echo "Error rendering prompt: {$e->getMessage()}\n";
}

// Create another prompt without a required variable
echo "Rendering with missing variable:\n";
echo "=============================\n";
try {
    echo $greetingPrompt->render([
        'name' => 'Jane',
        // 'place' is missing
        'day' => 'Tuesday',
    ]);
    echo "\n\n";
} catch (\PhpSwarm\Exception\Prompt\PromptRenderException $e) {
    echo "Error rendering prompt: {$e->getMessage()}\n";
}

// Create a direct prompt without a template
$directPrompt = $factory->createPrompt(
    'direct_prompt',
    'A direct prompt without a template',
    'This is a {{type}} prompt with a {{feature}} feature.',
    [
        'type' => [
            'description' => 'The type of prompt',
            'required' => true,
        ],
        'feature' => [
            'description' => 'The feature of the prompt',
            'required' => true,
        ],
    ],
    ['register_with_manager' => true]
);

// Render the direct prompt
echo "Rendered direct prompt:\n";
echo "=====================\n";
echo $directPrompt->render([
    'type' => 'simple',
    'feature' => 'flexible',
]);
echo "\n\n";

// Check registered prompts
echo "Registered prompts:\n";
echo "=================\n";
foreach ($promptManager->getPrompts() as $prompt) {
    echo "- {$prompt->getName()}: {$prompt->getDescription()}\n";
}
echo "\n";

// Using agent task template
$agentTaskPrompt = $factory->createPromptFromTemplate(
    'agent_task',
    'research_task',
    'A prompt for research tasks',
    [
        'role' => [
            'description' => 'The role of the agent',
            'required' => true,
        ],
        'goal' => [
            'description' => 'The goal of the agent',
            'required' => true,
        ],
        'task' => [
            'description' => 'The specific task to perform',
            'required' => true,
        ],
        'context' => [
            'description' => 'Additional context for the task',
            'required' => false,
        ],
    ]
);

// Render the agent task prompt
echo "Rendered agent task prompt:\n";
echo "=========================\n";
echo $agentTaskPrompt->render([
    'role' => 'Research Assistant',
    'goal' => 'Find relevant information about PHP frameworks',
    'task' => 'Compare Laravel and Symfony in terms of features and community support',
    'context' => 'The comparison will be used in a technical blog post.',
]); 