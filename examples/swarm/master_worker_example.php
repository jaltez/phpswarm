<?php

declare(strict_types=1);

/**
 * Example demonstrating the Master-Worker pattern with PHPSwarm.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpSwarm\Contract\Agent\AgentInterface;
use PhpSwarm\Contract\Agent\AgentResponseInterface;
use PhpSwarm\Swarm\Pattern\MasterWorkerPattern;
use PhpSwarm\Swarm\Swarm;

echo "=== PHPSwarm Master-Worker Pattern Example ===\n\n";

echo "This example demonstrates how agents can work together in a Master-Worker pattern:\n";
echo "- A master agent breaks down complex tasks into subtasks\n";
echo "- Worker agents with different specializations complete the subtasks\n";
echo "- The master agent aggregates the results into a final response\n\n";

echo "To run this example:\n";
echo "1. Implement your Agent and LLM classes\n";
echo "2. Create master and worker agent instances\n";
echo "3. Initialize the MasterWorkerPattern coordinator\n";
echo "4. Create a Swarm with the coordinator and agents\n";
echo "5. Run the swarm with your task\n\n";

echo "Example code structure:\n";
echo "```php\n";
echo '$masterAgent = /* Your agent implementation */;' . "\n";
echo '$workers = [/* Your worker agents */];' . "\n";
echo '$coordinator = new MasterWorkerPattern($masterAgent->getName());' . "\n";
echo '$swarm = new Swarm($coordinator, array_merge([$masterAgent], $workers));' . "\n";
echo '$response = $swarm->run("Your complex task here");' . "\n";
echo "```\n\n";

echo "See the class documentation for detailed implementation examples.\n";
