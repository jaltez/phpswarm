<?php

declare(strict_types=1);

namespace PhpSwarm\Contract\LLM;

/**
 * Interface for all LLM (Large Language Model) connectors.
 *
 * This interface provides a standard way to interact with different
 * LLM providers (OpenAI, Anthropic, etc.) in a consistent manner.
 */
interface LLMInterface
{
    /**
     * Send a chat completion request to the LLM.
     *
     * @param array<array<string, string>> $messages The messages to send
     * @param array<string, mixed> $options Additional options for the request
     * @return LLMResponseInterface The response from the LLM
     */
    public function chat(array $messages, array $options = []): LLMResponseInterface;

    /**
     * Send a completion (non-chat) request to the LLM.
     *
     * @param string $prompt The prompt to send
     * @param array<string, mixed> $options Additional options for the request
     * @return LLMResponseInterface The response from the LLM
     */
    public function complete(string $prompt, array $options = []): LLMResponseInterface;

    /**
     * Stream a response from the LLM, processing chunks as they arrive.
     *
     * @param array<array<string, string>> $messages The messages to send
     * @param callable $callback The callback to handle each chunk
     * @param array<string, mixed> $options Additional options for the request
     */
    public function stream(array $messages, callable $callback, array $options = []): void;

    /**
     * Get the number of tokens in the given input.
     *
     * @param string|array<mixed> $input The input to count tokens for
     * @return int The number of tokens
     */
    public function getTokenCount(string|array $input): int;

    /**
     * Get the default model name used by this connector.
     */
    public function getDefaultModel(): string;

    /**
     * Get the name of the provider (e.g., "OpenAI", "Anthropic").
     */
    public function getProviderName(): string;

    /**
     * Get whether this connector supports function calling.
     */
    public function supportsFunctionCalling(): bool;

    /**
     * Get whether this connector supports streaming.
     */
    public function supportsStreaming(): bool;

    /**
     * Get the maximum context length supported by the default model.
     */
    public function getMaxContextLength(): int;
}
