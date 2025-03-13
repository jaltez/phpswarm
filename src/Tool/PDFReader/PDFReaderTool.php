<?php

declare(strict_types=1);

namespace PhpSwarm\Tool\PDFReader;

use PhpSwarm\Exception\Tool\ToolExecutionException;
use PhpSwarm\Tool\BaseTool;

/**
 * A tool for extracting text content from PDF documents.
 */
class PDFReaderTool extends BaseTool
{
    /**
     * @var string The path to the pdftotext binary
     */
    private string $pdftotextPath;

    /**
     * Create a new PDFReaderTool instance.
     *
     * @param string|null $pdftotextPath The path to the pdftotext binary (optional)
     */
    public function __construct(?string $pdftotextPath = null)
    {
        parent::__construct(
            'pdf_reader',
            'Extract text content from a PDF document for analysis or processing'
        );

        $this->parametersSchema = [
            'file_path' => [
                'type' => 'string',
                'description' => 'The path to the PDF file to read',
                'required' => true,
            ],
            'page' => [
                'type' => 'integer',
                'description' => 'The specific page to extract (0 for all pages)',
                'required' => false,
                'default' => 0,
            ],
            'format' => [
                'type' => 'string',
                'description' => 'The output format (text, json)',
                'required' => false,
                'default' => 'text',
                'enum' => ['text', 'json'],
            ],
        ];

        // Try to find the pdftotext binary if not provided
        $this->pdftotextPath = $pdftotextPath ?? $this->findPdfToTextBinary();

        $this->addTag('pdf');
        $this->addTag('document');
        $this->addTag('reader');
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function run(array $parameters = []): mixed
    {
        $this->validateParameters($parameters);

        $filePath = $parameters['file_path'];
        $page = $parameters['page'] ?? 0;
        $format = $parameters['format'] ?? 'text';

        // Validate the file exists and is accessible
        if (!file_exists($filePath)) {
            throw new ToolExecutionException(
                "PDF file not found: {$filePath}",
                $parameters,
                $this->getName()
            );
        }

        if (!is_readable($filePath)) {
            throw new ToolExecutionException(
                "PDF file is not readable: {$filePath}",
                $parameters,
                $this->getName()
            );
        }

        try {
            // Check if we need to use external tools or PHP libraries
            if ($this->isExternalToolAvailable()) {
                $content = $this->extractTextUsingExternalTool($filePath, $page);
            } else {
                $content = $this->extractTextUsingPhp($filePath, $page);
            }

            // Format the output based on the requested format
            return match ($format) {
                'json' => $this->formatContentAsJson($content, $page),
                default => $content,
            };
        } catch (\Throwable $e) {
            throw new ToolExecutionException(
                "Failed to extract text from PDF: {$e->getMessage()}",
                $parameters,
                $this->getName(),
                0,
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function isAvailable() : bool
    {
        // Check if either external tools or PHP libraries are available
        if ($this->isExternalToolAvailable()) {
            return true;
        }
        return class_exists('Smalot\PdfParser\Parser');
    }

    /**
     * Check if the external pdftotext tool is available.
     */
    private function isExternalToolAvailable(): bool
    {
        return $this->pdftotextPath !== '' && $this->pdftotextPath !== '0' && file_exists($this->pdftotextPath) && is_executable($this->pdftotextPath);
    }

    /**
     * Try to find the pdftotext binary on the system.
     */
    private function findPdfToTextBinary(): string
    {
        // Common locations for pdftotext binary
        $commonPaths = [
            '/usr/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/opt/homebrew/bin/pdftotext',
            'C:\\Program Files\\poppler\\bin\\pdftotext.exe',
            'C:\\Program Files (x86)\\poppler\\bin\\pdftotext.exe',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try to find it in PATH
        $output = [];
        $returnVar = -1;

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            exec('where pdftotext 2>nul', $output, $returnVar);
        } else {
            // Unix/Linux/MacOS
            exec('which pdftotext 2>/dev/null', $output, $returnVar);
        }

        if ($returnVar === 0 && (isset($output[0]) && ($output[0] !== '' && $output[0] !== '0'))) {
            return $output[0];
        }

        return '';
    }

    /**
     * Extract text from a PDF using the external pdftotext tool.
     *
     * @param string $filePath The path to the PDF file
     * @param int $page The specific page to extract (0 for all pages)
     * @return string The extracted text
     * @throws ToolExecutionException If the extraction fails
     */
    private function extractTextUsingExternalTool(string $filePath, int $page): string
    {
        $command = escapeshellcmd($this->pdftotextPath);
        $escapedFilePath = escapeshellarg($filePath);
        $pageOption = $page > 0 ? "-f {$page} -l {$page}" : '';

        $outputFile = tempnam(sys_get_temp_dir(), 'pdf_');
        $escapedOutputFile = escapeshellarg($outputFile);

        $cmd = "{$command} {$pageOption} -layout {$escapedFilePath} {$escapedOutputFile} 2>&1";

        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            @unlink($outputFile);
            throw new ToolExecutionException(
                "pdftotext command failed: " . implode("\n", $output),
                ['file_path' => $filePath, 'page' => $page],
                $this->getName()
            );
        }

        $content = file_get_contents($outputFile);
        @unlink($outputFile);

        if ($content === false) {
            throw new ToolExecutionException(
                "Failed to read extracted text file",
                ['file_path' => $filePath, 'page' => $page],
                $this->getName()
            );
        }

        return $content;
    }

    /**
     * Extract text from a PDF using PHP libraries.
     *
     * @param string $filePath The path to the PDF file
     * @param int $page The specific page to extract (0 for all pages)
     * @return string The extracted text
     * @throws ToolExecutionException If the extraction fails
     */
    private function extractTextUsingPhp(string $filePath, int $page): string
    {
        // Check if the required library is available
        if (!class_exists('Smalot\PdfParser\Parser')) {
            throw new ToolExecutionException(
                "PDF parsing requires the smalot/pdfparser library. Install it with: composer require smalot/pdfparser",
                ['file_path' => $filePath],
                $this->getName()
            );
        }

        // Use the PDF Parser library
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);

        if ($page > 0) {
            // Extract text from a specific page
            $pages = $pdf->getPages();

            if (!isset($pages[$page - 1])) {
                throw new ToolExecutionException(
                    "Page {$page} not found in the PDF document",
                    ['file_path' => $filePath, 'page' => $page],
                    $this->getName()
                );
            }

            return $pages[$page - 1]->getText();
        }
        // Extract text from all pages
        return $pdf->getText();
    }

    /**
     * Format the extracted content as JSON with metadata.
     *
     * @param string $content The extracted text content
     * @param int $page The specific page that was extracted
     * @return array<string, mixed> The formatted content
     */
    private function formatContentAsJson(string $content, int $page): array
    {
        // Split the content into lines and paragraphs
        $lines = explode("\n", $content);
        $paragraphs = [];
        $currentParagraph = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || $trimmedLine === '0') {
                if ($currentParagraph !== '' && $currentParagraph !== '0') {
                    $paragraphs[] = trim($currentParagraph);
                    $currentParagraph = '';
                }
            } else {
                $currentParagraph .= ' ' . $trimmedLine;
            }
        }

        // Add the last paragraph if not empty
        if ($currentParagraph !== '' && $currentParagraph !== '0') {
            $paragraphs[] = trim($currentParagraph);
        }

        // Count words and characters
        $wordCount = str_word_count($content);
        $charCount = strlen($content);

        return [
            'content' => $content,
            'page' => $page > 0 ? $page : 'all',
            'paragraphs' => $paragraphs,
            'line_count' => count($lines),
            'paragraph_count' => count($paragraphs),
            'word_count' => $wordCount,
            'char_count' => $charCount,
        ];
    }

    /**
     * Set the path to the pdftotext binary.
     *
     * @param string $path The path to the pdftotext binary
     */
    public function setPdfToTextPath(string $path): self
    {
        $this->pdftotextPath = $path;
        return $this;
    }
}
