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
 * Command to scaffold a new Workflow class
 */
class MakeWorkflowCommand extends Command
{
    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('make:workflow')
            ->setDescription('Create a new Workflow class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the workflow class')
            ->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory to create the workflow in', 'src/Workflow')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'The description of the workflow', 'A custom workflow for PHPSwarm')
            ->addOption('steps', 's', InputOption::VALUE_OPTIONAL, 'Comma-separated list of step names', '');
    }

    /**
     * Execute the command
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $name = $input->getArgument('name');
        $directory = $input->getOption('directory');
        $description = $input->getOption('description');
        $stepsString = $input->getOption('steps');
        
        // Ensure the name has the correct format
        $className = $this->formatClassName($name);
        
        // Parse steps
        $steps = $this->parseSteps($stepsString);
        
        // Create the directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Generate the file path
        $filePath = $directory . '/' . $className . '.php';
        
        // Check if the file already exists
        if (file_exists($filePath)) {
            $io->error(sprintf('Workflow "%s" already exists at "%s"', $className, $filePath));
            return Command::FAILURE;
        }
        
        // Generate the namespace based on the directory
        $namespace = $this->generateNamespace($directory);
        
        // Generate the workflow class content
        $content = $this->generateWorkflowClass($namespace, $className, $description, $steps);
        
        // Write the content to the file
        file_put_contents($filePath, $content);
        
        $io->success(sprintf('Workflow "%s" created successfully at "%s"', $className, $filePath));
        
        return Command::SUCCESS;
    }
    
    /**
     * Format the class name to ensure it follows PHP conventions
     */
    private function formatClassName(string $name): string
    {
        // Remove "Workflow" suffix if present, we'll add it back later
        $name = preg_replace('/Workflow$/', '', $name);
        
        // Convert to PascalCase
        $name = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
        
        // Add "Workflow" suffix
        return $name . 'Workflow';
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
     * Parse the steps string into an array of step names
     */
    private function parseSteps(string $stepsString): array
    {
        if (empty($stepsString)) {
            return [];
        }
        
        $steps = [];
        $stepList = explode(',', $stepsString);
        
        foreach ($stepList as $step) {
            $step = trim($step);
            if (!empty($step)) {
                $steps[] = $step;
            }
        }
        
        return $steps;
    }
    
    /**
     * Generate the workflow class content
     */
    private function generateWorkflowClass(
        string $namespace,
        string $className,
        string $description,
        array $steps
    ): string {
        // Generate step definitions
        $stepDefinitions = $this->generateStepDefinitions($steps);
        
        // Generate step registrations
        $stepRegistrations = $this->generateStepRegistrations($steps);
        
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use PhpSwarm\Workflow\Workflow;
use PhpSwarm\Workflow\AgentStep;
use PhpSwarm\Workflow\FunctionStep;
use PhpSwarm\Contract\Workflow\WorkflowInterface;
use PhpSwarm\Contract\Workflow\WorkflowResultInterface;
use PhpSwarm\Contract\Logger\LoggerInterface;
use PhpSwarm\Contract\Logger\MonitorInterface;
use PhpSwarm\Factory\PhpSwarmFactory;

/**
 * {$className} - {$description}
 */
class {$className} extends Workflow
{
    /**
     * @var PhpSwarmFactory The factory for creating components
     */
    private PhpSwarmFactory \$factory;
    
    /**
     * Create a new {$className} instance
     *
     * @param string \$name The name of the workflow
     * @param string \$description The description of the workflow
     * @param LoggerInterface|null \$logger The logger to use
     * @param MonitorInterface|null \$monitor The monitor to use
     */
    public function __construct(
        string \$name = '{$this->getWorkflowName($className)}',
        string \$description = '{$description}',
        ?LoggerInterface \$logger = null,
        ?MonitorInterface \$monitor = null
    ) {
        parent::__construct(\$name, \$description, \$logger, \$monitor);
        
        \$this->factory = new PhpSwarmFactory();
        
        // Initialize the workflow
        \$this->initialize();
    }
    
    /**
     * Initialize the workflow with steps and dependencies
     */
    private function initialize(): void
    {
{$stepDefinitions}
        
        // Register steps
{$stepRegistrations}
        
        // Set dependencies between steps
        // Example: \$this->addDependency('step2', 'step1');
    }
    
    /**
     * Execute the workflow with custom pre/post processing
     *
     * @param array<string, mixed> \$input Initial input data for the workflow
     * @return WorkflowResultInterface The result of the workflow execution
     */
    public function execute(array \$input = []): WorkflowResultInterface
    {
        // Pre-execution processing
        \$enhancedInput = \$this->preprocessInput(\$input);
        
        // Execute the workflow
        \$result = parent::execute(\$enhancedInput);
        
        // Post-execution processing
        return \$this->postprocessResult(\$result);
    }
    
    /**
     * Preprocess the input data before workflow execution
     *
     * @param array<string, mixed> \$input The original input data
     * @return array<string, mixed> The processed input data
     */
    private function preprocessInput(array \$input): array
    {
        // Add any preprocessing logic here
        return \$input;
    }
    
    /**
     * Postprocess the workflow result
     *
     * @param WorkflowResultInterface \$result The original workflow result
     * @return WorkflowResultInterface The processed workflow result
     */
    private function postprocessResult(WorkflowResultInterface \$result): WorkflowResultInterface
    {
        // Add any postprocessing logic here
        return \$result;
    }
}
PHP;
    }
    
    /**
     * Get the workflow name from the class name
     */
    private function getWorkflowName(string $className): string
    {
        // Remove "Workflow" suffix
        $name = preg_replace('/Workflow$/', '', $className);
        
        // Add spaces before capital letters and trim
        return trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $name));
    }
    
    /**
     * Generate step definitions
     */
    private function generateStepDefinitions(array $steps): string
    {
        if (empty($steps)) {
            $steps = ['Step1', 'Step2'];
        }
        
        $definitions = '';
        
        foreach ($steps as $index => $step) {
            $stepId = $this->formatStepId($step);
            $stepName = $this->formatStepName($step);
            
            if ($index % 2 === 0) {
                // Create an agent step
                $definitions .= "        // Create agent step: {$stepName}\n";
                $definitions .= "        \$agent{$stepId} = \$this->factory->createAgent(\n";
                $definitions .= "            '{$stepName} Agent',\n";
                $definitions .= "            'Assistant',\n";
                $definitions .= "            'Help with {$stepName} task'\n";
                $definitions .= "        );\n";
                $definitions .= "        \n";
                $definitions .= "        \${$stepId} = new AgentStep(\n";
                $definitions .= "            '{$stepName}',\n";
                $definitions .= "            'Perform the {$stepName} task with the following data: {{data}}',\n";
                $definitions .= "            'Step to handle {$stepName}',\n";
                $definitions .= "            \$agent{$stepId}\n";
                $definitions .= "        );\n";
            } else {
                // Create a function step
                $definitions .= "        // Create function step: {$stepName}\n";
                $definitions .= "        \${$stepId} = new FunctionStep(\n";
                $definitions .= "            '{$stepName}',\n";
                $definitions .= "            function (array \$input) {\n";
                $definitions .= "                // Process the input data\n";
                $definitions .= "                \$processedData = \$input['data'] ?? 'No data provided';\n";
                $definitions .= "                \n";
                $definitions .= "                return [\n";
                $definitions .= "                    'result' => 'Processed: ' . \$processedData,\n";
                $definitions .= "                    'timestamp' => date('Y-m-d H:i:s'),\n";
                $definitions .= "                ];\n";
                $definitions .= "            },\n";
                $definitions .= "            'Function to process {$stepName}'\n";
                $definitions .= "        );\n";
            }
            
            if ($index < count($steps) - 1) {
                $definitions .= "        \n";
            }
        }
        
        return $definitions;
    }
    
    /**
     * Generate step registrations
     */
    private function generateStepRegistrations(array $steps): string
    {
        if (empty($steps)) {
            $steps = ['Step1', 'Step2'];
        }
        
        $registrations = '';
        
        foreach ($steps as $step) {
            $stepId = $this->formatStepId($step);
            $registrations .= "        \$this->addStep('{$stepId}', \${$stepId});\n";
        }
        
        return $registrations;
    }
    
    /**
     * Format a step name to a valid step ID
     */
    private function formatStepId(string $step): string
    {
        // Convert to camelCase
        $step = lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $step))));
        
        return $step;
    }
    
    /**
     * Format a step name for display
     */
    private function formatStepName(string $step): string
    {
        // Convert to Title Case with spaces
        return ucwords(str_replace(['_', '-'], ' ', $step));
    }
} 