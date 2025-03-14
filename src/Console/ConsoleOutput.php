<?php

declare(strict_types=1);

namespace PhpSwarm\Console;

/**
 * Utility class for colorful console output.
 */
class ConsoleOutput
{
    // Foreground colors
    public const FG_BLACK = '0;30';
    public const FG_RED = '0;31';
    public const FG_GREEN = '0;32';
    public const FG_YELLOW = '0;33';
    public const FG_BLUE = '0;34';
    public const FG_MAGENTA = '0;35';
    public const FG_CYAN = '0;36';
    public const FG_WHITE = '0;37';
    public const FG_DEFAULT = '0;39';
    
    // Bold foreground colors
    public const FG_BOLD_BLACK = '1;30';
    public const FG_BOLD_RED = '1;31';
    public const FG_BOLD_GREEN = '1;32';
    public const FG_BOLD_YELLOW = '1;33';
    public const FG_BOLD_BLUE = '1;34';
    public const FG_BOLD_MAGENTA = '1;35';
    public const FG_BOLD_CYAN = '1;36';
    public const FG_BOLD_WHITE = '1;37';
    
    // Background colors
    public const BG_BLACK = '40';
    public const BG_RED = '41';
    public const BG_GREEN = '42';
    public const BG_YELLOW = '43';
    public const BG_BLUE = '44';
    public const BG_MAGENTA = '45';
    public const BG_CYAN = '46';
    public const BG_WHITE = '47';
    public const BG_DEFAULT = '49';
    
    // Text styles
    public const STYLE_RESET = '0';
    public const STYLE_BOLD = '1';
    public const STYLE_DIM = '2';
    public const STYLE_ITALIC = '3';
    public const STYLE_UNDERLINE = '4';
    public const STYLE_BLINK = '5';
    public const STYLE_REVERSE = '7';
    public const STYLE_HIDDEN = '8';
    
    // Color theme for agent output
    public const THEME_AGENT_NAME = self::FG_BOLD_CYAN;
    public const THEME_AGENT_ROLE = self::FG_CYAN;
    public const THEME_AGENT_GOAL = self::FG_GREEN;
    public const THEME_AGENT_RESPONSE = self::FG_WHITE;
    public const THEME_AGENT_ERROR = self::FG_BOLD_RED;
    public const THEME_AGENT_SUCCESS = self::FG_BOLD_GREEN;
    public const THEME_AGENT_WARNING = self::FG_BOLD_YELLOW;
    public const THEME_AGENT_INFO = self::FG_BOLD_BLUE;
    public const THEME_AGENT_STATS = self::FG_MAGENTA;
    public const THEME_AGENT_TOOL = self::FG_YELLOW;
    public const THEME_AGENT_DEBUG = self::STYLE_DIM;
    
    /**
     * Check if the current terminal supports colors.
     */
    public static function supportsColor(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows supports colors in modern terminals, check for ConEmu, Windows Terminal,
            // or recent Windows 10 builds with ANSI color support
            return (
                false !== getenv('ANSICON') 
                || 'ON' === getenv('ConEmuANSI') 
                || 'xterm' === getenv('TERM')
                || false !== getenv('WT_SESSION')
            );
        }
        
        // For non-Windows systems
        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDOUT);
        }
        
        // If TERM exists and is not "dumb", assume color support
        return getenv('TERM') && getenv('TERM') !== 'dumb';
    }
    
    /**
     * Colorize the given text with ANSI color codes.
     *
     * @param string $text The text to colorize
     * @param string $color The color code to use
     * @param string|null $background Optional background color
     * @return string The colorized text
     */
    public static function colorize(string $text, string $color, ?string $background = null): string
    {
        if (!self::supportsColor()) {
            return $text;
        }
        
        $colored = "\033[" . $color . "m";
        
        if ($background !== null) {
            $colored .= "\033[" . $background . "m";
        }
        
        $colored .= $text . "\033[0m";
        
        return $colored;
    }
    
    /**
     * Format agent response for console output with appropriate colors.
     *
     * @param string $agentName The agent name
     * @param string $response The response text
     * @param bool $isSuccessful Whether the response was successful
     * @param array<string, int> $tokenUsage Token usage information
     * @param float $executionTime Execution time in seconds
     * @return string The formatted response
     */
    public static function formatAgentResponse(
        string $agentName,
        string $response,
        bool $isSuccessful = true,
        array $tokenUsage = [],
        float $executionTime = 0.0
    ): string {
        $output = '';
        
        // Format agent name
        $output .= self::colorize("[" . $agentName . "]", self::THEME_AGENT_NAME) . " ";
        
        // Format response based on success/error
        if ($isSuccessful) {
            $output .= self::colorize($response, self::THEME_AGENT_RESPONSE) . "\n";
        } else {
            $output .= self::colorize("Error: " . $response, self::THEME_AGENT_ERROR) . "\n";
        }
        
        // Add stats if provided
        if ($executionTime > 0 || !empty($tokenUsage)) {
            $output .= self::colorize("\n--- Stats ---", self::THEME_AGENT_STATS) . "\n";
            
            if ($executionTime > 0) {
                $formattedTime = $executionTime >= 1.0 
                    ? round($executionTime, 2) . " seconds" 
                    : round($executionTime * 1000) . "ms";
                $output .= self::colorize("Processing time: " . $formattedTime, self::THEME_AGENT_STATS) . "\n";
            }
            
            if (!empty($tokenUsage)) {
                $output .= self::colorize("Token usage: ", self::THEME_AGENT_STATS);
                
                $tokenUsageStr = [];
                foreach ($tokenUsage as $key => $count) {
                    $tokenUsageStr[] = "$key: $count";
                }
                
                $output .= self::colorize(implode(", ", $tokenUsageStr), self::THEME_AGENT_STATS) . "\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Format agent information for display.
     *
     * @param string $name Agent name
     * @param string $role Agent role
     * @param string $goal Agent goal
     * @param string $backstory Agent backstory
     * @param array $tools Agent tools
     * @return string Formatted agent information
     */
    public static function formatAgentInfo(
        string $name, 
        string $role, 
        string $goal, 
        string $backstory = '', 
        array $tools = []
    ): string {
        $output = '';
        
        // Agent header
        $output .= self::colorize("\n╔═══════════════════════════════════════════", self::THEME_AGENT_NAME) . "\n";
        $output .= self::colorize("║ ", self::THEME_AGENT_NAME) . self::colorize($name, self::FG_BOLD_WHITE) . "\n";
        $output .= self::colorize("╚═══════════════════════════════════════════", self::THEME_AGENT_NAME) . "\n\n";
        
        // Agent role
        $output .= self::colorize("Role: ", self::THEME_AGENT_NAME) . self::colorize($role, self::THEME_AGENT_ROLE) . "\n";
        
        // Agent goal
        $output .= self::colorize("Goal: ", self::THEME_AGENT_NAME) . self::colorize($goal, self::THEME_AGENT_GOAL) . "\n";
        
        // Backstory if available
        if (!empty($backstory)) {
            $output .= self::colorize("Backstory: ", self::THEME_AGENT_NAME) . self::colorize($backstory, self::THEME_AGENT_RESPONSE) . "\n";
        }
        
        // Tools if available
        if (!empty($tools)) {
            $output .= "\n" . self::colorize("Tools Available:", self::THEME_AGENT_NAME) . "\n";
            
            foreach ($tools as $tool) {
                if (is_object($tool) && method_exists($tool, 'getName') && method_exists($tool, 'getDescription')) {
                    $output .= self::colorize(" • ", self::THEME_AGENT_TOOL) . 
                              self::colorize($tool->getName(), self::FG_BOLD_YELLOW) . ": " . 
                              self::colorize($tool->getDescription(), self::THEME_AGENT_RESPONSE) . "\n";
                } elseif (is_array($tool) && isset($tool['name']) && isset($tool['description'])) {
                    $output .= self::colorize(" • ", self::THEME_AGENT_TOOL) . 
                              self::colorize($tool['name'], self::FG_BOLD_YELLOW) . ": " . 
                              self::colorize($tool['description'], self::THEME_AGENT_RESPONSE) . "\n";
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Format tool usage for display.
     *
     * @param string $toolName Name of the tool
     * @param mixed $input Tool input
     * @param mixed $output Tool output
     * @param bool $success Whether the tool execution was successful
     * @return string Formatted tool usage
     */
    public static function formatToolUsage(
        string $toolName, 
        mixed $input, 
        mixed $output = null, 
        bool $success = true
    ): string {
        $result = self::colorize("\n┌─ Tool Call: ", self::THEME_AGENT_TOOL) . 
                 self::colorize($toolName, self::FG_BOLD_YELLOW) . "\n";
        
        // Format input
        $result .= self::colorize("│  Input: ", self::THEME_AGENT_TOOL);
        
        if (is_array($input) || is_object($input)) {
            $result .= "\n" . self::colorize(json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), self::THEME_AGENT_RESPONSE);
        } else {
            $result .= self::colorize((string) $input, self::THEME_AGENT_RESPONSE);
        }
        
        // Format output if available
        if ($output !== null) {
            $result .= "\n" . self::colorize("│  Output: ", $success ? self::THEME_AGENT_TOOL : self::THEME_AGENT_ERROR);
            
            if (is_array($output) || is_object($output)) {
                $result .= "\n" . self::colorize(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 
                    $success ? self::THEME_AGENT_RESPONSE : self::THEME_AGENT_ERROR);
            } else {
                $result .= self::colorize((string) $output, $success ? self::THEME_AGENT_RESPONSE : self::THEME_AGENT_ERROR);
            }
        }
        
        $result .= "\n" . self::colorize("└───────────────────", self::THEME_AGENT_TOOL) . "\n";
        
        return $result;
    }
    
    /**
     * Format agent thinking/reasoning process.
     *
     * @param string $thinking The thinking/reasoning text
     * @return string Formatted thinking process
     */
    public static function formatThinking(string $thinking): string
    {
        $output = self::colorize("\n╭─ Thinking Process ─╮", self::THEME_AGENT_INFO) . "\n";
        $output .= self::colorize($thinking, self::THEME_AGENT_ROLE) . "\n";
        $output .= self::colorize("╰───────────────────╯", self::THEME_AGENT_INFO) . "\n";
        
        return $output;
    }
    
    /**
     * Output success message.
     */
    public static function success(string $message): string
    {
        return self::colorize("✓ " . $message, self::THEME_AGENT_SUCCESS);
    }
    
    /**
     * Output error message.
     */
    public static function error(string $message): string
    {
        return self::colorize("✗ " . $message, self::THEME_AGENT_ERROR);
    }
    
    /**
     * Output warning message.
     */
    public static function warning(string $message): string
    {
        return self::colorize("⚠ " . $message, self::THEME_AGENT_WARNING);
    }
    
    /**
     * Output info message.
     */
    public static function info(string $message): string
    {
        return self::colorize("ℹ " . $message, self::THEME_AGENT_INFO);
    }
    
    /**
     * Output tool usage message.
     */
    public static function tool(string $toolName, string $message): string
    {
        return self::colorize("[Tool: " . $toolName . "]", self::THEME_AGENT_TOOL) . " " . $message;
    }
    
    /**
     * Output debug message.
     */
    public static function debug(string $message): string
    {
        return self::colorize("🔍 " . $message, self::THEME_AGENT_DEBUG);
    }
} 