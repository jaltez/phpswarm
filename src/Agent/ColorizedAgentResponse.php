<?php

declare(strict_types=1);

namespace PhpSwarm\Agent;

use PhpSwarm\Console\ConsoleOutput;

/**
 * Extension of AgentResponse that provides colorized output for console display.
 */
class ColorizedAgentResponse extends AgentResponse
{
    /**
     * Output the response in a colorized format for console display.
     * 
     * @param string|null $agentName Optional agent name to display
     * @return string The colorized response
     */
    public function toColorizedString(?string $agentName = null): string
    {
        return ConsoleOutput::formatAgentResponse(
            $agentName ?? 'Agent',
            $this->getFinalAnswer(),
            $this->isSuccessful(),
            $this->getTokenUsage(),
            $this->getExecutionTime()
        );
    }
    
    /**
     * Format the trace for colorized display.
     * 
     * @return string The colorized trace
     */
    public function getColorizedTrace(): string
    {
        if ($this->getTrace() === []) {
            return '';
        }
        
        $output = ConsoleOutput::colorize("\n--- Execution Trace ---", ConsoleOutput::THEME_AGENT_INFO) . "\n";
        
        foreach ($this->getTrace() as $index => $traceItem) {
            $stepNumber = $index + 1;
            
            $output .= ConsoleOutput::colorize("Step $stepNumber:", ConsoleOutput::THEME_AGENT_INFO) . " ";
            
            if (isset($traceItem['type'])) {
                switch ($traceItem['type']) {
                    case 'tool':
                        if (isset($traceItem['tool_name']) && isset($traceItem['input'])) {
                            $output .= ConsoleOutput::formatToolUsage(
                                $traceItem['tool_name'],
                                $traceItem['input'],
                                $traceItem['output'] ?? null,
                                !isset($traceItem['error'])
                            );
                        }
                        break;
                    
                    case 'thought':
                        if (isset($traceItem['content'])) {
                            $output .= ConsoleOutput::formatThinking($traceItem['content']);
                        }
                        break;
                    
                    case 'stream':
                        $output .= ConsoleOutput::colorize(
                            "LLM interaction: Streaming",
                            ConsoleOutput::THEME_AGENT_ROLE
                        );
                        break;
                        
                    case 'chat':
                        $output .= ConsoleOutput::colorize(
                            "LLM interaction: Chat",
                            ConsoleOutput::THEME_AGENT_ROLE
                        );
                        break;
                    
                    default:
                        $output .= ConsoleOutput::colorize(
                            json_encode($traceItem, JSON_PRETTY_PRINT),
                            ConsoleOutput::THEME_AGENT_RESPONSE
                        );
                }
            } else {
                $output .= ConsoleOutput::colorize(
                    json_encode($traceItem, JSON_PRETTY_PRINT),
                    ConsoleOutput::THEME_AGENT_RESPONSE
                );
            }
            
            $output .= "\n";
        }
        
        return $output;
    }
    
    /**
     * Format all agent metadata in a colorized format.
     * 
     * @return string The colorized metadata
     */
    public function getColorizedMetadata(): string
    {
        $metadata = $this->getMetadata();
        
        if ($metadata === []) {
            return '';
        }
        
        $output = ConsoleOutput::colorize("\n--- Metadata ---", ConsoleOutput::THEME_AGENT_STATS) . "\n";
        
        foreach ($metadata as $key => $value) {
            $output .= ConsoleOutput::colorize("$key: ", ConsoleOutput::THEME_AGENT_STATS);
            
            if (is_array($value)) {
                $output .= ConsoleOutput::colorize(json_encode($value, JSON_PRETTY_PRINT), ConsoleOutput::THEME_AGENT_RESPONSE);
            } else {
                $output .= ConsoleOutput::colorize((string) $value, ConsoleOutput::THEME_AGENT_RESPONSE);
            }
            
            $output .= "\n";
        }
        
        return $output;
    }
} 