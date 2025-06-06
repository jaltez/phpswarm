<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Validation\Attribute;

use Attribute;

/**
 * Validates the type of a property.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Type
{
    /**
     * @param string $type The expected type (string, int, float, bool, array, object)
     * @param string|null $message Custom error message
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $message = null
    ) {}
}
