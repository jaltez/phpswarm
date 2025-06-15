<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Factory\PhpSwarmFactory;
use PhpSwarm\Utility\Embedding\OpenAIEmbeddingService;
use PhpSwarm\Memory\VectorMemory;

echo "Vector Memory Example\n";
echo "====================\n\n";

// Check for OpenAI API key
$apiKey = getenv('OPENAI_API_KEY');
if (empty($apiKey)) {
    echo "Error: OPENAI_API_KEY environment variable is required for this example.\n";
    echo "Please set your OpenAI API key:\n";
    echo "export OPENAI_API_KEY='your-api-key-here'\n";
    exit(1);
}

try {
    // Initialize Configuration
    $config = PhpSwarmConfig::getInstance();
    $factory = new PhpSwarmFactory($config);

    echo "Creating vector memory with OpenAI embeddings...\n";

    // Create embedding service
    $embeddingService = new OpenAIEmbeddingService([
        'api_key' => $apiKey,
        'model' => 'text-embedding-3-small',
    ]);

    // Create vector memory
    $vectorMemory = new VectorMemory($embeddingService);

    echo "Embedding service created with model: " . $embeddingService->getModelName() . "\n";
    echo "Vector dimension: " . $embeddingService->getDimension() . "\n\n";

    // Store some documents with semantic content
    echo "Adding documents to vector memory...\n";

    $documents = [
        'artificial_intelligence' => 'Artificial Intelligence (AI) is a branch of computer science that aims to create intelligent machines capable of performing tasks that typically require human intelligence.',
        'machine_learning' => 'Machine Learning is a subset of artificial intelligence that enables computers to learn and improve from experience without being explicitly programmed.',
        'deep_learning' => 'Deep Learning is a specialized subset of machine learning that uses neural networks with multiple layers to analyze various factors of data.',
        'natural_language_processing' => 'Natural Language Processing (NLP) is a field of artificial intelligence that focuses on the interaction between computers and human language.',
        'computer_vision' => 'Computer Vision is a field of artificial intelligence that trains computers to interpret and understand the visual world from digital images or videos.',
        'robotics' => 'Robotics is an interdisciplinary field that integrates computer science and engineering to design, construct, and operate robots.',
        'data_science' => 'Data Science is an interdisciplinary field that uses scientific methods, processes, algorithms and systems to extract knowledge from structured and unstructured data.',
        'blockchain' => 'Blockchain is a distributed ledger technology that maintains a continuously growing list of records linked and secured using cryptography.',
        'quantum_computing' => 'Quantum Computing is a type of computation that harnesses quantum mechanical phenomena to process information in fundamentally different ways.',
        'cybersecurity' => 'Cybersecurity is the practice of protecting systems, networks, and programs from digital attacks and unauthorized access.',
    ];

    foreach ($documents as $key => $content) {
        $vectorMemory->add($key, $content, [
            'category' => 'technology',
            'length' => strlen($content),
            'added_at' => date('Y-m-d H:i:s'),
        ]);
        echo "  Added: {$key}\n";
    }

    echo "\nVector memory now contains " . $vectorMemory->size() . " documents.\n\n";

    // Demonstrate semantic search
    echo "Performing semantic searches...\n";
    echo "================================\n\n";

    $searchQueries = [
        'neural networks and learning algorithms',
        'understanding human language',
        'protecting from hackers',
        'robots and automation',
        'analyzing images and pictures',
    ];

    foreach ($searchQueries as $query) {
        echo "Query: \"$query\"\n";
        echo "Results:\n";

        $results = $vectorMemory->semanticSearch($query, 3, 0.7); // Top 3 results with 70% similarity threshold

        if (empty($results)) {
            echo "  No results found above similarity threshold.\n";
        } else {
            foreach ($results as $key => $result) {
                $similarity = round($result['score'] * 100, 2);
                echo "  - {$key}: {$similarity}% similarity\n";
                echo "    Content: " . substr($result['value'], 0, 100) . "...\n";
            }
        }
        echo "\n";
    }

    // Demonstrate finding similar documents
    echo "Finding documents similar to 'machine_learning'...\n";
    echo "=================================================\n";

    $similarDocs = $vectorMemory->findSimilar('machine_learning', 3, 0.5);

    foreach ($similarDocs as $key => $result) {
        $similarity = round($result['score'] * 100, 2);
        echo "- {$key}: {$similarity}% similarity\n";
        echo "  Content: " . substr($result['value'], 0, 80) . "...\n\n";
    }

    // Show vector statistics
    echo "Vector Memory Statistics:\n";
    echo "========================\n";
    $stats = $vectorMemory->getVectorStats();
    foreach ($stats as $key => $value) {
        if ($key === 'memory_usage') {
            echo "- {$key}: " . round($value / 1024 / 1024, 2) . " MB\n";
        } else {
            echo "- {$key}: {$value}\n";
        }
    }

    echo "\nExample completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
