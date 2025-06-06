<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Validation\Attribute;

use Attribute;

/**
 * Validates that a numeric value is within a specific range.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Range
{
    /**
     * @param int|float|null $min Minimum value (inclusive)
     * @param int|float|null $max Maximum value (inclusive)
     * @param string|null $message Custom error message
     */
    public function __construct(
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
        public readonly ?string $message = null
    ) {}
}
