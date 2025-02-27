<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;

// Initialize Configuration
$config = PhpSwarmConfig::getInstance();

// Create the factory
$factory = new PhpSwarmFactory($config);

echo "Workflow Engine Example\n";
echo "======================\n\n";

// Create a logger and monitor
echo "Creating logger and performance monitor...\n";
$logger = $factory->createLogger([
    'log_file' => __DIR__ . '/../logs/workflow-example.log',
    'min_level' => 'debug',
]);

$monitor = $factory->createMonitor([
    'logger' => $logger,
]);

// Create the agents for our workflow
echo "Creating agents for the workflow...\n";

// Researcher agent looks up information
$researcherAgent = $factory->createAgent(
    'Researcher',
    'Research Assistant',
    'Find accurate information on topics',
    [
        'backstory' => 'You are an expert researcher who focuses on finding accurate and relevant information.',
        'llm' => [
            'model' => 'gpt-4', // or another appropriate model
        ],
        'tools' => ['web_search', 'calculator'],
        'verbose_logging' => true,
    ]
);

// Writer agent creates content based on research
$writerAgent = $factory->createAgent(
    'Writer',
    'Content Writer',
    'Create well-structured content based on research',
    [
        'backstory' => 'You are a professional writer who specializes in creating clear and engaging content.',
        'llm' => [
            'model' => 'gpt-4', // or another appropriate model
        ],
        'tools' => ['calculator'],
        'verbose_logging' => true,
    ]
);

// Editor agent reviews and improves content
$editorAgent = $factory->createAgent(
    'Editor',
    'Content Editor',
    'Polish and improve content for clarity and accuracy',
    [
        'backstory' => 'You are a detail-oriented editor who ensures content is polished, error-free, and effective.',
        'llm' => [
            'model' => 'gpt-4', // or another appropriate model
        ],
        'tools' => [],
        'verbose_logging' => true,
    ]
);

// Create a workflow for content creation
echo "Creating content creation workflow...\n";
$workflow = $factory->createWorkflow(
    'Content Creation',
    'A workflow to research, write, and edit content on a given topic',
    [
        'logger' => $logger,
        'monitor' => $monitor,
        'max_parallel_steps' => 1, // Execute steps sequentially
    ]
);

// Add workflow steps
echo "Adding steps to the workflow...\n";

// 1. Research step
$researchStep = $factory->createAgentStep(
    'Research Topic',
    'Research the following topic and provide key facts, statistics, and insights: {topic}. Focus on recent information when available.',
    'Gather information on the specified topic',
    $researcherAgent
);
$researchStep->setInputMapping([
    'topic' => 'research_topic',
]);
$researchStep->setOutputMapping([
    'content' => 'research_results',
]);
$workflow->addStep('research', $researchStep);

// 2. Data processing step (using a function)
$processDataStep = $factory->createFunctionStep(
    'Process Research Data',
    function(array $input, array $stepsOutput) {
        $researchResults = $stepsOutput['research']['content'] ?? 'No research data available';
        
        // Extract key points (simplified example)
        $lines = explode("\n", $researchResults);
        $keyPoints = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Look for lines that might be key points (containing numbers, statistics, etc.)
            if (preg_match('/\d+/', $line) || str_contains($line, ':')) {
                $keyPoints[] = $line;
            }
        }
        
        // If we didn't find many points, just take the first few lines
        if (count($keyPoints) < 3 && count($lines) > 3) {
            $keyPoints = array_slice($lines, 0, 3);
        }
        
        return [
            'key_points' => $keyPoints,
            'word_count' => str_word_count($researchResults),
            'processed_timestamp' => date('Y-m-d H:i:s'),
        ];
    },
    'Process and structure research data for the writer'
);
$workflow->addStep('process_data', $processDataStep);
$workflow->addDependency('process_data', 'research');

// 3. Writing step
$writeStep = $factory->createAgentStep(
    'Write Draft',
    'Write a well-structured article about {topic} using the following research: {research_results}. Include the following key points: {key_points}. The article should be engaging and informative.',
    'Create the initial content draft',
    $writerAgent
);
$writeStep->setInputMapping([
    'topic' => 'research_topic',
    'research_results' => 'research_results',
    'key_points' => 'key_points',
]);
$writeStep->setOutputMapping([
    'content' => 'draft_content',
]);
$workflow->addStep('write', $writeStep);
$workflow->addDependency('write', 'process_data');

// 4. Editing step
$editStep = $factory->createAgentStep(
    'Edit Content',
    'Edit and improve the following draft article about {topic}: {draft_content}. Focus on clarity, flow, and accuracy. Make sure all key points from the research are included correctly.',
    'Polish and finalize the content',
    $editorAgent
);
$editStep->setInputMapping([
    'topic' => 'research_topic',
    'draft_content' => 'draft_content',
]);
$editStep->setOutputMapping([
    'content' => 'final_content',
]);
$workflow->addStep('edit', $editStep);
$workflow->addDependency('edit', 'write');

// 5. Final formatting (using another function)
$formatStep = $factory->createFunctionStep(
    'Format Content',
    function(array $input, array $stepsOutput) {
        $finalContent = $stepsOutput['edit']['content'] ?? 'No content available';
        $topic = $input['research_topic'] ?? 'Unknown Topic';
        
        $formattedContent = "# " . strtoupper($topic) . "\n\n";
        $formattedContent .= $finalContent;
        $formattedContent .= "\n\n---\n";
        $formattedContent .= "Generated on: " . date('Y-m-d H:i:s');
        
        // Add some stats
        $wordCount = str_word_count($finalContent);
        $paragraphCount = substr_count($finalContent, "\n\n") + 1;
        
        $stats = [
            'word_count' => $wordCount,
            'paragraph_count' => $paragraphCount,
            'reading_time' => ceil($wordCount / 200) . ' minutes', // Assuming 200 words per minute
        ];
        
        return [
            'formatted_content' => $formattedContent,
            'stats' => $stats,
        ];
    },
    'Format the final content for presentation'
);
$workflow->addStep('format', $formatStep);
$workflow->addDependency('format', 'edit');

// Execute the workflow
echo "Executing workflow...\n\n";

$processId = $monitor->beginProcess('content_creation_example', [
    'workflow' => 'Content Creation',
    'start_time' => date('Y-m-d H:i:s'),
]);

$logger->info("Starting content creation workflow", [
    'process_id' => $processId,
]);

// Set a sample topic
$topic = "The Impact of Artificial Intelligence on Modern Healthcare";

try {
    // Execute the workflow
    $timerId = $monitor->startTimer('workflow_execution');
    
    $result = $workflow->execute([
        'research_topic' => $topic,
    ]);
    
    $executionTime = $monitor->stopTimer($timerId);
    
    $logger->info("Workflow completed", [
        'success' => $result->isSuccessful(),
        'execution_time' => $executionTime,
    ]);
    
    // Display result summary
    echo "Workflow execution completed.\n";
    echo "Success: " . ($result->isSuccessful() ? "Yes" : "No") . "\n";
    echo "Execution time: " . $result->getExecutionTime() . " seconds\n";
    echo "Steps completed: " . count($result->getStepResults()) . "\n";
    
    if (!empty($result->getErrors())) {
        echo "\nErrors encountered:\n";
        foreach ($result->getErrors() as $stepId => $error) {
            echo "  - Step '$stepId': $error\n";
        }
    }
    
    // Display content stats if available
    if (isset($result->getOutput()['stats'])) {
        $stats = $result->getOutput()['stats'];
        echo "\nContent Statistics:\n";
        echo "  - Word Count: " . $stats['word_count'] . "\n";
        echo "  - Paragraph Count: " . $stats['paragraph_count'] . "\n";
        echo "  - Estimated Reading Time: " . $stats['reading_time'] . "\n";
    }
    
    // Save the formatted content to a file if available
    if (isset($result->getOutput()['formatted_content'])) {
        $outputPath = __DIR__ . '/../output/article_' . date('Ymd_His') . '.md';
        $outputDir = dirname($outputPath);
        
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        file_put_contents($outputPath, $result->getOutput()['formatted_content']);
        echo "\nFormatted content saved to: $outputPath\n";
        
        $logger->info("Content saved to file", [
            'file_path' => $outputPath,
        ]);
    }
    
    // Show performance metrics
    echo "\nPerformance Metrics:\n";
    $metrics = $monitor->getMetrics();
    
    foreach ($metrics as $key => $value) {
        if (is_scalar($value)) {
            echo "  - $key: $value\n";
        }
    }
    
    $monitor->endProcess($processId, [
        'success' => $result->isSuccessful(),
        'execution_time' => $result->getExecutionTime(),
    ]);
} catch (\Throwable $e) {
    $logger->error("Workflow execution failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    $monitor->failProcess($processId, $e->getMessage(), [
        'exception' => get_class($e),
    ]);
    
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nWorkflow example completed.\n";
echo "Check the log file at: " . __DIR__ . "/../logs/workflow-example.log\n"; 