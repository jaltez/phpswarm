<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\Tool;

/**
 * Interface for all tools that agents can use.
 *
 * A tool is a capability provided to an agent that allows it
 * to interact with the world or perform specific functions.
 */
interface ToolInterface
{
    /**
     * Execute the tool with the given parameters.
     *
     * @param array<string, mixed> $parameters The parameters for the tool
     * @return mixed The result of the tool execution
     * @throws \PhpSwarm\Exception\Tool\ToolExecutionException If the tool execution fails
     */
    public function run(array $parameters = []): mixed;

    /**
     * Get the name of the tool.
     */
    public function getName(): string;

    /**
     * Get the description of the tool.
     */
    public function getDescription(): string;

    /**
     * Get the parameters schema for the tool.
     *
     * @return array<string, array<string, mixed>> The parameters schema as a JSON Schema compatible array
     */
    public function getParametersSchema(): array;

    /**
     * Check if the tool is available for use.
     *
     * This can be used to check if the tool requires external resources
     * that might not be available, or if it depends on certain conditions.
     */
    public function isAvailable(): bool;

    /**
     * Get whether the tool requires authentication.
     */
    public function requiresAuthentication(): bool;

    /**
     * Tag this tool to categorize it.
     *
     * @param string $tag The tag to add
     */
    public function addTag(string $tag): self;

    /**
     * Get the tags associated with this tool.
     *
     * @return array<string>
     */
    public function getTags(): array;
}
