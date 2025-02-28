<?php

declare(strict_types=1);

namespace PhpSwarm\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to scaffold a new Agent class
 */
class MakeAgentCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('make:agent')
            ->setDescription('Create a new Agent class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the agent class')
            ->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory to create the agent in', 'src/Agent')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'The role of the agent', 'Assistant')
            ->addOption('goal', 'g', InputOption::VALUE_OPTIONAL, 'The goal of the agent', 'Help users with their tasks')
            ->addOption('backstory', 'b', InputOption::VALUE_OPTIONAL, 'The backstory of the agent', '');
    }

    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $directory = $input->getOption('directory');
        $role = $input->getOption('role');
        $goal = $input->getOption('goal');
        $backstory = $input->getOption('backstory');

        // Ensure the name has the correct format
        $className = $this->formatClassName($name);

        // Create the directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate the file path
        $filePath = $directory . '/' . $className . '.php';

        // Check if the file already exists
        if (file_exists($filePath)) {
            $io->error(sprintf('Agent "%s" already exists at "%s"', $className, $filePath));
            return Command::FAILURE;
        }

        // Generate the namespace based on the directory
        $namespace = $this->generateNamespace($directory);

        // Generate the agent class content
        $content = $this->generateAgentClass($namespace, $className, $role, $goal, $backstory);

        // Write the content to the file
        file_put_contents($filePath, $content);

        $io->success(sprintf('Agent "%s" created successfully at "%s"', $className, $filePath));

        return Command::SUCCESS;
    }

    /**
     * Format the class name to ensure it follows PHP conventions
     */
    private function formatClassName(string $name): string
    {
        // Remove "Agent" suffix if present, we'll add it back later
        $name = preg_replace('/Agent$/', '', $name);

        // Convert to PascalCase
        $name = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));

        // Add "Agent" suffix
        return $name . 'Agent';
    }

    /**
     * Generate the namespace based on the directory
     */
    private function generateNamespace(string $directory): string
    {
        // Convert directory path to namespace
        $namespace = str_replace('/', '\\', $directory);

        // Remove src/ or src\ prefix
        $namespace = preg_replace('/^src[\/\\\\]/', '', $namespace);

        // Add PhpSwarm prefix
        return 'PhpSwarm\\' . $namespace;
    }

    /**
     * Generate the agent class content
     */
    private function generateAgentClass(
        string $namespace,
        string $className,
        string $role,
        string $goal,
        string $backstory
    ): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use PhpSwarm\Agent\Agent;
use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\LLM\LLMInterface;
use PhpSwarm\Contract\Memory\MemoryInterface;
use PhpSwarm\Contract\Tool\ToolInterface;

/**
 * {$className} - Custom agent implementation
 */
class {$className} extends Agent
{
    /**
     * Create a new {$className} instance
     *
     * @param string \$name The name of the agent
     * @param LLMInterface|null \$llm The LLM to use
     * @param MemoryInterface|null \$memory The memory system to use
     */
    public function __construct(
        string \$name = '{$className}',
        ?LLMInterface \$llm = null,
        ?MemoryInterface \$memory = null
    ) {
        parent::__construct(
            \$name,
            '{$role}',
            '{$goal}',
            '{$backstory}',
            \$llm,
            \$memory
        );
        
        // Add default tools here
        // \$this->addTool(new SomeTool());
    }
    
    /**
     * Initialize the agent with custom configuration
     *
     * @return void
     */
    protected function initialize(): void
    {
        // Custom initialization logic here
    }
    
    /**
     * Custom method example - add your own methods here
     *
     * @param string \$task The task to analyze
     * @return string The analysis result
     */
    public function analyzeTask(string \$task): string
    {
        // Custom task analysis logic
        return "Analysis of: {\$task}";
    }
}
PHP;
    }
}
