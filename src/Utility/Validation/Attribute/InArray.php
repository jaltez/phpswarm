<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Validation\Attribute;

use Attribute;

/**
 * Validates that a value is in a specific array of allowed values.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class InArray
{
    /**
     * @param array<mixed> $values Array of allowed values
     * @param bool $strict Whether to use strict comparison
     * @param string|null $message Custom error message
     */
    public function __construct(
        public readonly array $values,
        public readonly bool $strict = true,
        public readonly ?string $message = null
    ) {}
}
