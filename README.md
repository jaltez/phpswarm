# PHPSwarm: Modern AI Agentic Framework for PHP

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

PHPSwarm is a modern PHP framework for building AI-powered applications using autonomous agents. It provides a clean, intuitive API for creating, managing, and deploying AI agents that can perform tasks, make decisions, collaborate with other agents, and use tools to accomplish objectives.

## 🚀 Features

- **Simple, Intuitive API**: Build complex agent systems with minimal code
- **Modern PHP Design**: Fully leverages PHP 8.3+ features
- **Flexible LLM Support**: Connect to OpenAI, Anthropic, and other LLM providers
- **Extendable Tools System**: Create custom tools with minimal boilerplate
- **Memory Management**: Multiple persistent memory providers (Array, Redis, SQLite)
- **Agent Collaboration**: Create swarms of agents that work together
- **Workflow Engine**: Orchestrate complex multi-agent processes with dependencies
- **FileSystem Tools**: Securely read, write, and manage files and directories
- **Logging & Monitoring**: Comprehensive logging and performance tracking
- **Environment Configuration**: Easily manage configuration with .env support

## 📋 Requirements

- PHP 8.3 or higher
- Composer 2.0 or higher
- OpenAI API key or other supported LLM provider

## 🔧 Installation

```bash
composer require jaltez/phpswarm
```

## 📘 Quick Start

```php
use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;

// Initialize configuration
$config = PhpSwarmConfig::getInstance();

// Create the factory
$factory = new PhpSwarmFactory($config);

// Create an agent
$agent = $factory->createAgent(
    'Research Assistant',
    'Research Assistant',
    'Research topics and provide accurate information',
    [
        'llm' => [
            'provider' => 'openai',
            'model' => 'gpt-4',
        ],
        'tools' => ['web_search', 'calculator'],
        'memory' => [
            'provider' => 'sqlite',
            'ttl' => 3600,
        ],
    ]
);

// Run the agent
$response = $agent->run("What are the latest developments in fusion energy?");

// Get the result
echo $response->getContent();
```

## Memory Providers

PHPSwarm supports multiple memory providers:

```php
// In-memory array (default)
$memory = $factory->createMemory(['provider' => 'array']);

// Redis for shared, persistent memory
$memory = $factory->createMemory([
    'provider' => 'redis',
    'host' => 'localhost',
    'port' => 6379,
]);

// SQLite for file-based persistent memory
$memory = $factory->createMemory([
    'provider' => 'sqlite',
    'db_path' => 'storage/memory.sqlite',
]);
```

## Workflow Engine

Orchestrate complex processes with multiple agents:

```php
// Create a workflow
$workflow = $factory->createWorkflow(
    'Content Creation',
    'A workflow to research, write, and edit content'
);

// Add steps
$researchStep = $factory->createAgentStep(
    'Research Topic',
    'Research the topic {topic} and provide insights',
    'Gather information',
    $researcherAgent
);

$writeStep = $factory->createAgentStep(
    'Write Content',
    'Write an article about {topic} using this research: {research}',
    'Create draft content',
    $writerAgent
);

// Add steps to workflow
$workflow->addStep('research', $researchStep);
$workflow->addStep('write', $writeStep);

// Add dependencies
$workflow->addDependency('write', 'research');

// Execute workflow
$result = $workflow->execute([
    'topic' => 'Artificial Intelligence in Healthcare',
]);
```

## 📊 Project Status

PHPSwarm is in active development with regular feature additions.

## 📚 Documentation

See the [examples](examples/) directory for more detailed usage examples.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📜 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details. 