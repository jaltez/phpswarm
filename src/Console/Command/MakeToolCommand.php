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
 * Command to scaffold a new Tool class
 */
class MakeToolCommand extends Command
{
    /**
     * Configure the command
     */
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('make:tool')
            ->setDescription('Create a new Tool class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the tool class')
            ->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory to create the tool in', 'src/Tool')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'The description of the tool', 'A custom tool for PHPSwarm')
            ->addOption('parameters', 'p', InputOption::VALUE_OPTIONAL, 'Comma-separated list of parameters (name:type:description)', '');
    }

    /**
     * Execute the command
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $directory = $input->getOption('directory');
        $description = $input->getOption('description');
        $parametersString = $input->getOption('parameters');

        // Ensure the name has the correct format
        $className = $this->formatClassName($name);

        // Parse parameters
        $parameters = $this->parseParameters($parametersString);

        // Create the directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);

            // If it's a new tool type, create a subdirectory
            if (str_starts_with((string) $directory, 'src/Tool/') && substr_count((string) $directory, '/') === 2) {
                $subDir = basename((string) $directory);
                $directory = $directory . '/' . $subDir;
                mkdir($directory, 0755, true);
            }
        }

        // Generate the file path
        $filePath = $directory . '/' . $className . '.php';

        // Check if the file already exists
        if (file_exists($filePath)) {
            $io->error(sprintf('Tool "%s" already exists at "%s"', $className, $filePath));
            return Command::FAILURE;
        }

        // Generate the namespace based on the directory
        $namespace = $this->generateNamespace($directory);

        // Generate the tool class content
        $content = $this->generateToolClass($namespace, $className, $description, $parameters);

        // Write the content to the file
        file_put_contents($filePath, $content);

        $io->success(sprintf('Tool "%s" created successfully at "%s"', $className, $filePath));

        return Command::SUCCESS;
    }

    /**
     * Format the class name to ensure it follows PHP conventions
     */
    private function formatClassName(string $name): string
    {
        // Remove "Tool" suffix if present, we'll add it back later
        $name = preg_replace('/Tool$/', '', $name);

        // Convert to PascalCase
        $name = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));

        // Add "Tool" suffix
        return $name . 'Tool';
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
     * Parse the parameters string into an array of parameter definitions
     */
    private function parseParameters(string $parametersString): array
    {
        if ($parametersString === '' || $parametersString === '0') {
            return [];
        }

        $parameters = [];
        $paramList = explode(',', $parametersString);

        foreach ($paramList as $param) {
            $parts = explode(':', trim($param));

            $name = $parts[0] ?? '';
            $type = $parts[1] ?? 'string';
            $description = $parts[2] ?? 'Parameter description';

            if ($name !== '' && $name !== '0') {
                $parameters[] = [
                    'name' => $name,
                    'type' => $type,
                    'description' => $description,
                ];
            }
        }

        return $parameters;
    }

    /**
     * Generate the tool class content
     */
    private function generateToolClass(
        string $namespace,
        string $className,
        string $description,
        array $parameters
    ): string {
        // Generate parameters schema
        $parametersSchema = $this->generateParametersSchema($parameters);

        // Generate parameter validation
        $paramValidation = $this->generateParameterValidation($parameters);

        // Generate parameter properties
        $paramProperties = $this->generateParameterProperties($parameters);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use PhpSwarm\Tool\BaseTool;
use PhpSwarm\Exception\Tool\ToolExecutionException;

/**
 * {$className} - {$description}
 */
class {$className} extends BaseTool
{
    /**
     * Create a new {$className} instance
     */
    public function __construct()
    {
        parent::__construct(
            '{$this->getToolName($className)}',
            '{$description}'
        );
    }
    
    /**
     * Get the parameters schema for this tool
     *
     * @return array<string, array<string, mixed>>
     */
    public function getParametersSchema(): array
    {
        return {$parametersSchema};
    }
    
    /**
     * Execute the tool with the given parameters
     *
     * @param array<string, mixed> \$parameters The parameters for the tool
     * @return mixed The result of the tool execution
     * @throws ToolExecutionException If the tool execution fails
     */
    public function run(array \$parameters = []): mixed
    {
        // Validate parameters
        \$this->validateParameters(\$parameters);
        
{$paramValidation}
        try {
            // Implement your tool logic here
            \$result = "Tool executed successfully";
            
            return \$result;
        } catch (\Exception \$e) {
            throw new ToolExecutionException(
                "Failed to execute {$className}: " . \$e->getMessage(),
                previous: \$e,
                toolName: \$this->getName()
            );
        }
    }
    
    /**
     * Check if the tool is available for use
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        // Check if any required resources are available
        return true;
    }
{$paramProperties}
}
PHP;
    }

    /**
     * Get the tool name from the class name
     */
    private function getToolName(string $className): string
    {
        // Remove "Tool" suffix
        $name = preg_replace('/Tool$/', '', $className);

        // Add spaces before capital letters and trim
        return trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', (string) $name));
    }

    /**
     * Generate the parameters schema code
     */
    private function generateParametersSchema(array $parameters): string
    {
        if ($parameters === []) {
            return '[]';
        }

        $schema = "[\n";

        foreach ($parameters as $param) {
            $schema .= "            '{$param['name']}' => [\n";
            $schema .= "                'type' => '{$param['type']}',\n";
            $schema .= "                'description' => '{$param['description']}',\n";
            $schema .= "                'required' => true,\n";
            $schema .= "            ],\n";
        }

        return $schema . "        ]";
    }

    /**
     * Generate parameter validation code
     */
    private function generateParameterValidation(array $parameters): string
    {
        if ($parameters === []) {
            return '';
        }

        $validation = '';

        foreach ($parameters as $param) {
            $name = $param['name'];
            $validation .= "        \${$name} = \$parameters['{$name}'] ?? null;\n";
        }

        return $validation;
    }

    /**
     * Generate parameter properties
     */
    private function generateParameterProperties(array $parameters): string
    {
        if ($parameters === []) {
            return '';
        }

        $properties = "\n";

        foreach ($parameters as $param) {
            $name = $param['name'];
            $type = $param['type'];
            $description = $param['description'];

            $properties .= "    /**\n";
            $properties .= "     * Helper method to get the {$name} parameter\n";
            $properties .= "     *\n";
            $properties .= "     * @param array<string, mixed> \$parameters The parameters array\n";
            $properties .= "     * @return {$type}|null The {$name} value\n";
            $properties .= "     */\n";
            $properties .= "    private function get" . ucfirst((string) $name) . "(array \$parameters): ?" . $type . "\n";
            $properties .= "    {\n";
            $properties .= "        return \$parameters['{$name}'] ?? null;\n";
            $properties .= "    }\n\n";
        }

        return $properties;
    }
}
