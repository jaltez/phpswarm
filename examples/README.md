# PHPSwarm Examples

This directory contains example scripts that demonstrate how to use various features of the PHPSwarm framework. Each example is designed to showcase specific functionality and serve as a starting point for your own applications.

## Prerequisites

Before running these examples, ensure you have:

- PHP 8.3 or higher installed
- Composer dependencies installed (`composer install` in the project root)
- Any required API keys set as environment variables (see Configuration section below)

## Available Examples

### Basic Examples

- **[simple-agent.php](simple-agent.php)**: Shows the most basic agent setup and usage
- **[factory-example.php](factory-example.php)**: Demonstrates using the factory to create agents
- **[env-config-example.php](env-config-example.php)**: Shows how to use environment variables for configuration

### LLM Providers

- **[anthropic-example.php](anthropic-example.php)**: Shows how to use Claude models via the Anthropic connector

### Memory Systems

- **[redis-memory-example.php](redis-memory-example.php)**: Shows how to use Redis for persistent memory
- **[sqlite-memory-example.php](sqlite-memory-example.php)**: Shows how to use SQLite for file-based persistent memory

### Tools

- **[file-system-tool-example.php](file-system-tool-example.php)**: Demonstrates how to use the FileSystem tool

### Performance and Monitoring

- **[logging-monitoring-example.php](logging-monitoring-example.php)**: Shows how to use the logging and performance monitoring systems

### Advanced Examples

- **[multi-tool-agent.php](multi-tool-agent.php)**: Shows how to create an agent with multiple tools
- **[workflow-example.php](workflow-example.php)**: Demonstrates the workflow engine with multiple agents and steps, plus logging and monitoring

## Running Examples

To run any example, simply use the PHP CLI:

```bash
php examples/simple-agent.php
```

## Configuration

Many examples require API keys or other configuration. You can set these using environment variables:

### For OpenAI Examples
```bash
export OPENAI_API_KEY=your_openai_api_key_here
```

### For Anthropic (Claude) Examples
```bash
export ANTHROPIC_API_KEY=your_anthropic_api_key_here
```

### For Weather Tool Examples
```bash
export WEATHER_API_KEY=your_weather_api_key_here
```

### For Web Search Tool Examples
```bash
export SEARCH_API_KEY=your_google_search_api_key_here
export SEARCH_ENGINE_ID=your_google_search_engine_id_here
```

### For Redis Memory Examples
```bash
export REDIS_HOST=localhost
export REDIS_PORT=6379
```

## Creating Your Own Examples

You can use these examples as a starting point for your own applications. The basic pattern for using PHPSwarm is:

1. Create an agent with the appropriate configuration
2. Add any tools the agent will need
3. Run the agent with a specific task

For more advanced usage, see the `factory-example.php` and `workflow-example.php` files, which demonstrate how to use the factory pattern and orchestrate multiple agents in a workflow. 