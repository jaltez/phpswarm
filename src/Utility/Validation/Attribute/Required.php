<?php

declare(strict_types=1);

namespace PhpSwarm\Utility\Validation\Attribute;

use Attribute;

/**
 * Marks a property as required during validation.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Required
{
    /**
     * @param string|null $message Custom error message
     */
    public function __construct(
        public readonly ?string $message = null
    ) {}
}
