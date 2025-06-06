<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Validation\Attribute;

use Attribute;

/**
 * Validates that a string matches a regex pattern.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Pattern
{
    /**
     * @param string $pattern The regex pattern
     * @param string|null $message Custom error message
     */
    public function __construct(
        public readonly string $pattern,
        public readonly ?string $message = null
    ) {}
}
