<?php
/**
 * SynapseHRAG - Quick Start Example
 *
 * This example demonstrates basic HRAG usage for hierarchical semantic search.
 */

require __DIR__ . '/../../vendor/autoload.php';

use SynapseHRAG\Engine;

// Database connection (PostgreSQL with pgvector)
$pdo = new PDO(
    'pgsql:host=localhost;dbname=synapse_hrag',
    'your_user',
    'your_password'
);

// Initialize HRAG Engine
$hrag = new Engine($pdo);

// Example 1: Basic Search
echo "=== Example 1: Basic Search ===\n";
$results = $hrag->search("What is hierarchical RAG?");
foreach ($results as $result) {
    echo "Level: {$result['level']}\n";
    echo "Content: {$result['content']}\n";
    echo "Confidence: {$result['confidence']}\n\n";
}

// Example 2: Search with Intent
echo "=== Example 2: Intent-Based Search ===\n";
$results = $hrag->search(
    query: "How to optimize database performance?",
    intent: "technical_optimization",
    threshold: 0.75
);
print_r($results);

// Example 3: Multi-Level Results
echo "=== Example 3: Hierarchical Results ===\n";
$results = $hrag->neuralSearch(
    query: "Explain neural networks",
    levels: ['strategic', 'tactical', 'operational']
);

foreach ($results as $level => $items) {
    echo "\n{$level} Level:\n";
    foreach ($items as $item) {
        echo "  - {$item['content']}\n";
    }
}

// Example 4: Learning from Interaction
echo "=== Example 4: Hebbian Learning ===\n";
$query = "What is HRAG?";
$results = $hrag->search($query);

// Provide feedback to strengthen neural pathways
$hrag->learn(
    query: $query,
    selected_result_id: $results[0]['id'],
    relevance_score: 0.95
);

echo "Neural pathways updated!\n";
