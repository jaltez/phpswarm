<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwarm\Factory\PhpSwarmFactory;
use PhpSwarm\Config\PhpSwarmConfig;
use PhpSwarm\Utility\Event\AgentEvent;
use PhpSwarm\Utility\Event\ToolEvent;
use PhpSwarm\Utility\Event\WorkflowEvent;
use PhpSwarm\Utility\Event\BaseEventListener;
use PhpSwarm\Contract\Utility\EventInterface;
use PhpSwarm\Contract\Utility\EventSubscriberInterface;

echo "=== PHPSwarm Events System Example ===\n\n";

// Initialize factory and configuration
$config = PhpSwarmConfig::getInstance();
$factory = new PhpSwarmFactory($config);

// 1. Create Event Dispatcher
echo "1. Creating Event Dispatcher...\n";
$dispatcher = $factory->createEventDispatcher([
    'enable_logging' => true,
    'log_all_events' => true
]);
echo "✓ Event dispatcher created with logging enabled\n\n";

// 2. Create Custom Event Listener
echo "2. Creating Custom Event Listener...\n";

class MetricsEventListener extends BaseEventListener
{
    private array $metrics = [];

    public function __construct()
    {
        parent::__construct(50); // Medium priority
        $this->setSubscribedEvents([
            AgentEvent::AGENT_TASK_COMPLETED,
            AgentEvent::AGENT_TASK_FAILED,
            ToolEvent::TOOL_EXECUTION_COMPLETED,
            ToolEvent::TOOL_EXECUTION_FAILED,
            WorkflowEvent::WORKFLOW_COMPLETED,
            WorkflowEvent::WORKFLOW_FAILED,
        ]);
    }

    public function handle(EventInterface $event): void
    {
        $eventName = $event->getName();
        $data = $event->getData();

        switch ($eventName) {
            case AgentEvent::AGENT_TASK_COMPLETED:
                $this->metrics['agent_tasks_completed'] = ($this->metrics['agent_tasks_completed'] ?? 0) + 1;
                $this->metrics['total_agent_execution_time'] = ($this->metrics['total_agent_execution_time'] ?? 0) + ($data['execution_time'] ?? 0);
                break;

            case AgentEvent::AGENT_TASK_FAILED:
                $this->metrics['agent_tasks_failed'] = ($this->metrics['agent_tasks_failed'] ?? 0) + 1;
                break;

            case ToolEvent::TOOL_EXECUTION_COMPLETED:
                $this->metrics['tool_executions_completed'] = ($this->metrics['tool_executions_completed'] ?? 0) + 1;
                $this->metrics['total_tool_execution_time'] = ($this->metrics['total_tool_execution_time'] ?? 0) + ($data['execution_time'] ?? 0);
                break;

            case ToolEvent::TOOL_EXECUTION_FAILED:
                $this->metrics['tool_executions_failed'] = ($this->metrics['tool_executions_failed'] ?? 0) + 1;
                break;

            case WorkflowEvent::WORKFLOW_COMPLETED:
                $this->metrics['workflows_completed'] = ($this->metrics['workflows_completed'] ?? 0) + 1;
                break;

            case WorkflowEvent::WORKFLOW_FAILED:
                $this->metrics['workflows_failed'] = ($this->metrics['workflows_failed'] ?? 0) + 1;
                break;
        }

        echo "📊 Metrics updated for event: {$eventName}\n";
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }
}

$metricsListener = new MetricsEventListener();
$dispatcher->addListener(AgentEvent::AGENT_TASK_COMPLETED, $metricsListener, 50);
$dispatcher->addListener(AgentEvent::AGENT_TASK_FAILED, $metricsListener, 50);
$dispatcher->addListener(ToolEvent::TOOL_EXECUTION_COMPLETED, $metricsListener, 50);
$dispatcher->addListener(ToolEvent::TOOL_EXECUTION_FAILED, $metricsListener, 50);
$dispatcher->addListener(WorkflowEvent::WORKFLOW_COMPLETED, $metricsListener, 50);
$dispatcher->addListener(WorkflowEvent::WORKFLOW_FAILED, $metricsListener, 50);

echo "✓ Custom metrics listener created and registered\n\n";

// 3. Create Event Subscriber
echo "3. Creating Event Subscriber...\n";

class PerformanceEventSubscriber implements EventSubscriberInterface
{
    private array $slowOperations = [];

    public function getSubscribedEvents(): array
    {
        return [
            AgentEvent::AGENT_TASK_COMPLETED => ['onTaskCompleted', 10],
            ToolEvent::TOOL_EXECUTION_COMPLETED => ['onToolCompleted', 10],
            WorkflowEvent::WORKFLOW_COMPLETED => ['onWorkflowCompleted', 10],
        ];
    }

    public function onTaskCompleted(EventInterface $event): void
    {
        $data = $event->getData();
        $executionTime = $data['execution_time'] ?? 0;

        if ($executionTime > 5.0) { // Slow if > 5 seconds
            $this->slowOperations[] = [
                'type' => 'agent_task',
                'name' => $data['agent_name'] ?? 'unknown',
                'task' => $data['task'] ?? 'unknown',
                'execution_time' => $executionTime,
                'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s')
            ];
            echo "⚠️  Slow agent task detected: {$data['task']} took {$executionTime}s\n";
        }
    }

    public function onToolCompleted(EventInterface $event): void
    {
        $data = $event->getData();
        $executionTime = $data['execution_time'] ?? 0;

        if ($executionTime > 2.0) { // Slow if > 2 seconds
            $this->slowOperations[] = [
                'type' => 'tool_execution',
                'name' => $data['tool_name'] ?? 'unknown',
                'execution_time' => $executionTime,
                'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s')
            ];
            echo "⚠️  Slow tool execution detected: {$data['tool_name']} took {$executionTime}s\n";
        }
    }

    public function onWorkflowCompleted(EventInterface $event): void
    {
        $data = $event->getData();
        $executionTime = $data['execution_time'] ?? 0;

        if ($executionTime > 10.0) { // Slow if > 10 seconds
            $this->slowOperations[] = [
                'type' => 'workflow',
                'name' => $data['workflow_name'] ?? 'unknown',
                'execution_time' => $executionTime,
                'timestamp' => $event->getTimestamp()->format('Y-m-d H:i:s')
            ];
            echo "⚠️  Slow workflow detected: {$data['workflow_name']} took {$executionTime}s\n";
        }
    }

    public function getSlowOperations(): array
    {
        return $this->slowOperations;
    }
}

$performanceSubscriber = new PerformanceEventSubscriber();
$dispatcher->addSubscriber($performanceSubscriber);

echo "✓ Performance monitoring subscriber created and registered\n\n";

// 4. Create and dispatch some events
echo "4. Dispatching Sample Events...\n\n";

// Create a mock agent for events
$agent = $factory->createAgent('TestAgent', 'Assistant', 'Help with testing');

// Agent events
echo "Dispatching agent events:\n";
$event1 = AgentEvent::taskStarted($agent, 'Analyze data', ['context' => 'test']);
$dispatcher->dispatch($event1);

$event2 = AgentEvent::taskCompleted($agent, 'Analyze data', ['result' => 'success'], 2.5);
$dispatcher->dispatch($event2);

$event3 = AgentEvent::taskCompleted($agent, 'Complex analysis', ['result' => 'comprehensive'], 6.2); // This should trigger slow operation warning
$dispatcher->dispatch($event3);

// Tool events
echo "\nDispatching tool events:\n";
$calculatorTool = $factory->createTool('calculator');

$event4 = ToolEvent::executionStarted($calculatorTool, ['expression' => '2 + 2']);
$dispatcher->dispatch($event4);

$event5 = ToolEvent::executionCompleted($calculatorTool, 4, 0.1, ['expression' => '2 + 2']);
$dispatcher->dispatch($event5);

$event6 = ToolEvent::executionCompleted($calculatorTool, 42, 3.5, ['expression' => 'complex calculation']); // This should trigger slow operation warning
$dispatcher->dispatch($event6);

// Workflow events
echo "\nDispatching workflow events:\n";
$workflow = $factory->createWorkflow('test-workflow', 'Test workflow for events');

$event7 = WorkflowEvent::workflowStarted($workflow, ['context' => 'testing']);
$dispatcher->dispatch($event7);

$event8 = WorkflowEvent::stepStarted($workflow, 'step1', ['data' => 'test']);
$dispatcher->dispatch($event8);

$event9 = WorkflowEvent::stepCompleted($workflow, 'step1', 'success', 1.2);
$dispatcher->dispatch($event9);

$event10 = WorkflowEvent::workflowCompleted($workflow, 'completed', 12.5); // This should trigger slow operation warning
$dispatcher->dispatch($event10);

echo "\n";

// 5. Create a stoppable event and demonstrate stopping
echo "5. Demonstrating Event Stopping...\n";

// Add a listener that stops the event
$dispatcher->addListener('test.stoppable', function (EventInterface $event) {
    echo "🛑 First listener: Stopping event propagation\n";
    $event->stopPropagation();
}, 100); // High priority

$dispatcher->addListener('test.stoppable', function (EventInterface $event) {
    echo "👻 Second listener: This should not be called\n";
}, 50); // Lower priority

$stoppableEvent = $factory->createEvent('test.stoppable', ['test' => 'data'], 'test', true);
$dispatcher->dispatch($stoppableEvent);

echo "✓ Event was stopped by first listener\n\n";

// 6. Demonstrate callable listeners
echo "6. Demonstrating Callable Listeners...\n";

$dispatcher->addListener('test.callable', function (EventInterface $event) {
    $data = $event->getData();
    echo "📞 Callable listener received event with data: " . json_encode($data) . "\n";
});

$callableEvent = $factory->createEvent('test.callable', ['message' => 'Hello from callable!']);
$dispatcher->dispatch($callableEvent);

echo "\n";

// 7. Display collected metrics
echo "7. Final Metrics and Performance Data...\n\n";

echo "📊 Performance Metrics:\n";
$metrics = $metricsListener->getMetrics();
foreach ($metrics as $metric => $value) {
    echo "  • {$metric}: {$value}\n";
}

echo "\n⚠️  Slow Operations Detected:\n";
$slowOps = $performanceSubscriber->getSlowOperations();
if (empty($slowOps)) {
    echo "  • No slow operations detected\n";
} else {
    foreach ($slowOps as $op) {
        echo "  • {$op['type']}: {$op['name']} took {$op['execution_time']}s at {$op['timestamp']}\n";
    }
}

// 8. Demonstrate listener management
echo "\n8. Demonstrating Listener Management...\n";

echo "Current listeners for 'test.callable': " . count($dispatcher->getListeners('test.callable')) . "\n";

// Remove all listeners for this event
$dispatcher->removeAllListeners('test.callable');
echo "After removal: " . count($dispatcher->getListeners('test.callable')) . "\n";

// Test that no listeners are called
$testEvent = $factory->createEvent('test.callable', ['message' => 'Should not be processed']);
$dispatcher->dispatch($testEvent);
echo "✓ No listeners called for removed event\n\n";

// 9. Demonstrate hasListeners
echo "9. Checking Listener Availability...\n";
echo "Has listeners for 'agent.task.started': " . ($dispatcher->hasListeners(AgentEvent::AGENT_TASK_STARTED) ? 'Yes' : 'No') . "\n";
echo "Has listeners for 'non.existent.event': " . ($dispatcher->hasListeners('non.existent.event') ? 'Yes' : 'No') . "\n\n";

echo "=== Events System Example Complete ===\n";
echo "\nKey Features Demonstrated:\n";
echo "✓ Event creation and dispatching\n";
echo "✓ Custom event listeners with priorities\n";
echo "✓ Event subscribers with multiple subscriptions\n";
echo "✓ Event propagation stopping\n";
echo "✓ Callable listeners\n";
echo "✓ Metrics collection through events\n";
echo "✓ Performance monitoring\n";
echo "✓ Listener management (add/remove)\n";
echo "✓ Automatic logging integration\n";
echo "✓ Event metadata and enrichment\n";
