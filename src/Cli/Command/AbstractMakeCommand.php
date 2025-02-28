<?php

declare(strict_types=1);

namespace PhpSwarm\Cli\Command;

use PhpSwarm\Config\PhpSwarmConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Abstract base class for all "make" commands.
 */
abstract class AbstractMakeCommand extends Command
{
    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var PhpSwarmConfig|null
     */
    protected ?PhpSwarmConfig $config;

    /**
     * Create a new make command.
     */
    public function __construct()
    {
        parent::__construct();
        $this->filesystem = new Filesystem();
        $this->config = PhpSwarmConfig::getInstance();
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            return $this->handle($input, $output, $io);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Handle the command execution.
     */
    abstract protected function handle(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io
    ): int;

    /**
     * Get the project root directory.
     */
    protected function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Get the source directory.
     */
    protected function getSourceDir(): string
    {
        return $this->getProjectDir() . '/src';
    }

    /**
     * Create a directory if it does not exist.
     */
    protected function createDirectory(string $directory): void
    {
        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory, 0755);
        }
    }

    /**
     * Create a file from a template.
     */
    protected function createFileFromTemplate(
        string $destination,
        string $template,
        array $replacements = []
    ): void {
        if ($this->filesystem->exists($destination)) {
            throw new \RuntimeException(sprintf('File "%s" already exists.', $destination));
        }

        $content = file_get_contents($template);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Unable to read template file "%s".', $template));
        }

        foreach ($replacements as $key => $value) {
            $content = str_replace('{{ ' . $key . ' }}', $value, $content);
        }

        $this->filesystem->dumpFile($destination, $content);
    }

    /**
     * Generate a file from a string template.
     */
    protected function generateFile(
        string $destination,
        string $template,
        array $replacements = []
    ): void {
        if ($this->filesystem->exists($destination)) {
            throw new \RuntimeException(sprintf('File "%s" already exists.', $destination));
        }

        foreach ($replacements as $key => $value) {
            $template = str_replace('{{ ' . $key . ' }}', $value, $template);
        }

        $this->filesystem->dumpFile($destination, $template);
    }
}
