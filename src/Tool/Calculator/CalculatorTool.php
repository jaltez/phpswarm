<?php

declare(strict_types=1);

namespace PhpSwarm\Tool\Calculator;

use PhpSwarm\Exception\Tool\ToolExecutionException;
use PhpSwarm\Tool\BaseTool;

/**
 * A tool for performing basic arithmetic calculations.
 */
class CalculatorTool extends BaseTool
{
    /**
     * Create a new CalculatorTool instance.
     */
    public function __construct()
    {
        parent::__construct(
            'calculator',
            'Performs basic arithmetic calculations (+, -, *, /, ^, sqrt, etc.)'
        );

        $this->parametersSchema = [
            'expression' => [
                'type' => 'string',
                'description' => 'The mathematical expression to evaluate (e.g., "2 + 2", "sqrt(16)", "2^3")',
                'required' => true,
            ],
        ];

        $this->addTag('math');
        $this->addTag('calculation');
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function run(array $parameters = []): mixed
    {
        $this->validateParameters($parameters);

        $expression = $parameters['expression'];

        try {
            // Sanitize the input expression
            $sanitizedExpression = $this->sanitizeExpression($expression);

            // Evaluate the expression
            return $this->evaluate($sanitizedExpression);
        } catch (\Throwable $e) {
            throw new ToolExecutionException(
                "Failed to evaluate expression: {$e->getMessage()}",
                $parameters,
                $this->getName(),
                0,
                $e
            );
        }
    }

    /**
     * Sanitize the expression to prevent code injection.
     *
     * @throws ToolExecutionException If the expression contains invalid characters
     */
    private function sanitizeExpression(string $expression): string
    {
        // Remove whitespace
        $expression = trim($expression);

        // Only allow safe characters
        if (!preg_match('/^[0-9+\-*\/\^\(\)\s\.,sqrt]+$/i', $expression)) {
            throw new ToolExecutionException(
                'Expression contains invalid characters. Only numbers, basic operators (+, -, *, /, ^), parentheses, and function sqrt() are allowed.',
                $expression,
                $this->getName()
            );
        }

        return $expression;
    }

    /**
     * Safely evaluate the mathematical expression.
     *
     * @throws ToolExecutionException If the expression cannot be evaluated
     */
    private function evaluate(string $expression): float
    {
        // Replace sqrt with the PHP equivalent
        $expression = preg_replace('/sqrt\s*\(\s*([0-9\.]+)\s*\)/i', 'sqrt($1)', $expression);

        // Replace ^ with ** for exponentiation
        $expression = str_replace('^', '**', $expression);

        // Create a safe evaluation environment
        $mathFunctions = [
            'sqrt' => 'sqrt',
            'abs' => 'abs',
            'sin' => 'sin',
            'cos' => 'cos',
            'tan' => 'tan',
            'log' => 'log',
            'exp' => 'exp',
        ];

        // Build a safe evaluation string
        $code = '';
        foreach ($mathFunctions as $alias => $function) {
            $code .= "function $alias(\$x) { return $function(\$x); }";
        }

        $code .= "return $expression;";

        // Evaluate the expression
        try {
            $result = eval($code);

            if ($result === false) {
                throw new ToolExecutionException(
                    'Error evaluating the expression',
                    $expression,
                    $this->getName()
                );
            }

            return (float) $result;
        } catch (\ParseError $e) {
            throw new ToolExecutionException(
                'Invalid mathematical expression: ' . $e->getMessage(),
                $expression,
                $this->getName(),
                0,
                $e
            );
        }
    }
}
