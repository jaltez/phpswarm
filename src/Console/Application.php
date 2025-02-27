<?php

declare(strict_types=1);

namespace PhpSwarm\Console;

use Symfony\Component\Console\Application as SymfonyApplication;
use PhpSwarm\Console\Command\MakeAgentCommand;
use PhpSwarm\Console\Command\MakeToolCommand;
use PhpSwarm\Console\Command\MakeWorkflowCommand;
use PhpSwarm\Console\Command\MakeMemoryCommand;
use PhpSwarm\Console\Command\MakeLLMConnectorCommand;

/**
 * PHPSwarm CLI Application
 */
class Application extends SymfonyApplication
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('PHPSwarm CLI', '1.0.0');
        
        $this->registerCommands();
    }
    
    /**
     * Register all available commands
     */
    private function registerCommands(): void
    {
        $this->add(new MakeAgentCommand());
        $this->add(new MakeToolCommand());
        $this->add(new MakeWorkflowCommand());
        $this->add(new MakeMemoryCommand());
        $this->add(new MakeLLMConnectorCommand());
    }
} 