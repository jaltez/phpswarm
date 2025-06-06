<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Validation\Attribute;

use Attribute;

/**
 * Validates the length of a string or array.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Length
{
    /**
     * @param int|null $min Minimum length
     * @param int|null $max Maximum length
     * @param int|null $exact Exact length
     * @param string|null $message Custom error message
     */
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?int $exact = null,
        public readonly ?string $message = null
    ) {}
}
