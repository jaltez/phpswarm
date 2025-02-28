<?php

declare(strict_types=1);

namespace PhpSwarm\Cli;

use PhpSwarm\Cli\Command\MakeAgentCommand;
use PhpSwarm\Cli\Command\MakeToolCommand;
use PhpSwarm\Cli\Command\MakeMemoryCommand;
use PhpSwarm\Cli\Command\MakeLLMConnectorCommand;
use PhpSwarm\Config\PhpSwarmConfig;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * PHPSwarm CLI Application.
 */
class Application extends SymfonyApplication
{
    /**
     * @var PhpSwarmConfig|null
     */
    private ?PhpSwarmConfig $config;

    /**
     * Create a new PHPSwarm CLI application.
     */
    public function __construct(?PhpSwarmConfig $config = null)
    {
        parent::__construct('PHPSwarm CLI', '1.0.0');

        $this->config = $config ?? PhpSwarmConfig::getInstance();
        $this->registerCommands();
    }

    /**
     * Register all available commands.
     */
    private function registerCommands(): void
    {
        $commands = [
            new MakeAgentCommand(),
            new MakeToolCommand(),
            new MakeMemoryCommand(),
            new MakeLLMConnectorCommand(),
        ];

        foreach ($commands as $command) {
            $this->add($command);
        }
    }

    /**
     * Get the configuration.
     */
    public function getConfig(): PhpSwarmConfig
    {
        return $this->config;
    }
}
