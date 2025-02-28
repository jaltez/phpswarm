<?php

declare(strict_types=1);

namespace PhpSwarm\Performance;

/**
 * Class for tracking security-related metrics.
 */
class SecurityMetrics
{
    /**
     * @var array<string, int> Counters for various security events
     */
    private array $counters = [];
    
    /**
     * @var array<string, array<string, mixed>> Details about recent security events
     */
    private array $recentEvents = [];
    
    /**
     * @var int Maximum number of recent events to store
     */
    private int $maxRecentEvents;
    
    /**
     * Create a new SecurityMetrics instance.
     *
     * @param int $maxRecentEvents Maximum number of recent events to store
     */
    public function __construct(int $maxRecentEvents = 100)
    {
        $this->maxRecentEvents = $maxRecentEvents;
        $this->initializeCounters();
    }
    
    /**
     * Record a security event.
     *
     * @param string $type The type of event (e.g., 'input_validation', 'path_safety', 'prompt_injection')
     * @param string $result The result of the check ('passed', 'failed', 'blocked')
     * @param array<string, mixed> $details Additional details about the event
     * @return self
     */
    public function recordEvent(string $type, string $result, array $details = []): self
    {
        // Increment the appropriate counter
        $counterKey = $type . '_' . $result;
        if (isset($this->counters[$counterKey])) {
            $this->counters[$counterKey]++;
        } else {
            $this->counters[$counterKey] = 1;
        }
        
        // Also increment the total for this type
        $totalKey = $type . '_total';
        if (isset($this->counters[$totalKey])) {
            $this->counters[$totalKey]++;
        } else {
            $this->counters[$totalKey] = 1;
        }
        
        // Add to recent events
        $this->recentEvents[] = [
            'timestamp' => time(),
            'type' => $type,
            'result' => $result,
            'details' => $details,
        ];
        
        // Trim recent events if needed
        if (count($this->recentEvents) > $this->maxRecentEvents) {
            array_shift($this->recentEvents);
        }
        
        return $this;
    }
    
    /**
     * Record an input validation event.
     *
     * @param bool $passed Whether validation passed
     * @param array<string, mixed> $details Details about the validation
     * @return self
     */
    public function recordInputValidation(bool $passed, array $details = []): self
    {
        return $this->recordEvent(
            'input_validation',
            $passed ? 'passed' : 'failed',
            $details
        );
    }
    
    /**
     * Record a path safety check event.
     *
     * @param bool $passed Whether the path was deemed safe
     * @param array<string, mixed> $details Details about the check
     * @return self
     */
    public function recordPathSafetyCheck(bool $passed, array $details = []): self
    {
        return $this->recordEvent(
            'path_safety',
            $passed ? 'passed' : 'failed',
            $details
        );
    }
    
    /**
     * Record a prompt injection detection event.
     *
     * @param bool $injectionDetected Whether injection was detected
     * @param array<string, mixed> $details Details about the detection
     * @return self
     */
    public function recordPromptInjectionCheck(bool $injectionDetected, array $details = []): self
    {
        return $this->recordEvent(
            'prompt_injection',
            $injectionDetected ? 'detected' : 'clean',
            $details
        );
    }
    
    /**
     * Record a command safety check event.
     *
     * @param bool $passed Whether the command was deemed safe
     * @param array<string, mixed> $details Details about the check
     * @return self
     */
    public function recordCommandSafetyCheck(bool $passed, array $details = []): self
    {
        return $this->recordEvent(
            'command_safety',
            $passed ? 'passed' : 'failed',
            $details
        );
    }
    
    /**
     * Get all security counters.
     *
     * @return array<string, int> The counters
     */
    public function getCounters(): array
    {
        return $this->counters;
    }
    
    /**
     * Get the value of a specific counter.
     *
     * @param string $key The counter key
     * @return int The counter value or 0 if not found
     */
    public function getCounter(string $key): int
    {
        return $this->counters[$key] ?? 0;
    }
    
    /**
     * Get recent security events.
     *
     * @param int|null $limit Maximum number of events to return (null for all)
     * @param string|null $type Filter by event type
     * @param string|null $result Filter by event result
     * @return array<array<string, mixed>> The recent events
     */
    public function getRecentEvents(?int $limit = null, ?string $type = null, ?string $result = null): array
    {
        $events = $this->recentEvents;
        
        // Filter by type if specified
        if ($type !== null) {
            $events = array_filter($events, function ($event) use ($type) {
                return $event['type'] === $type;
            });
        }
        
        // Filter by result if specified
        if ($result !== null) {
            $events = array_filter($events, function ($event) use ($result) {
                return $event['result'] === $result;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($events, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        // Apply limit if specified
        if ($limit !== null && $limit > 0) {
            $events = array_slice($events, 0, $limit);
        }
        
        return $events;
    }
    
    /**
     * Get security statistics.
     *
     * @return array<string, mixed> Statistics about security events
     */
    public function getStatistics(): array
    {
        $stats = [
            'input_validation' => [
                'total' => $this->getCounter('input_validation_total'),
                'passed' => $this->getCounter('input_validation_passed'),
                'failed' => $this->getCounter('input_validation_failed'),
                'pass_rate' => 0,
            ],
            'path_safety' => [
                'total' => $this->getCounter('path_safety_total'),
                'passed' => $this->getCounter('path_safety_passed'),
                'failed' => $this->getCounter('path_safety_failed'),
                'pass_rate' => 0,
            ],
            'prompt_injection' => [
                'total' => $this->getCounter('prompt_injection_total'),
                'clean' => $this->getCounter('prompt_injection_clean'),
                'detected' => $this->getCounter('prompt_injection_detected'),
                'clean_rate' => 0,
            ],
            'command_safety' => [
                'total' => $this->getCounter('command_safety_total'),
                'passed' => $this->getCounter('command_safety_passed'),
                'failed' => $this->getCounter('command_safety_failed'),
                'pass_rate' => 0,
            ],
            'total_events' => count($this->recentEvents),
        ];
        
        // Calculate rates
        if ($stats['input_validation']['total'] > 0) {
            $stats['input_validation']['pass_rate'] = 
                $stats['input_validation']['passed'] / $stats['input_validation']['total'] * 100;
        }
        
        if ($stats['path_safety']['total'] > 0) {
            $stats['path_safety']['pass_rate'] = 
                $stats['path_safety']['passed'] / $stats['path_safety']['total'] * 100;
        }
        
        if ($stats['prompt_injection']['total'] > 0) {
            $stats['prompt_injection']['clean_rate'] = 
                $stats['prompt_injection']['clean'] / $stats['prompt_injection']['total'] * 100;
        }
        
        if ($stats['command_safety']['total'] > 0) {
            $stats['command_safety']['pass_rate'] = 
                $stats['command_safety']['passed'] / $stats['command_safety']['total'] * 100;
        }
        
        return $stats;
    }
    
    /**
     * Reset all counters and clear recent events.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->initializeCounters();
        $this->recentEvents = [];
        return $this;
    }
    
    /**
     * Initialize counters with default values.
     *
     * @return void
     */
    private function initializeCounters(): void
    {
        $this->counters = [
            // Input validation
            'input_validation_total' => 0,
            'input_validation_passed' => 0,
            'input_validation_failed' => 0,
            
            // Path safety
            'path_safety_total' => 0,
            'path_safety_passed' => 0,
            'path_safety_failed' => 0,
            
            // Prompt injection
            'prompt_injection_total' => 0,
            'prompt_injection_clean' => 0,
            'prompt_injection_detected' => 0,
            
            // Command safety
            'command_safety_total' => 0,
            'command_safety_passed' => 0,
            'command_safety_failed' => 0,
        ];
    }
} 