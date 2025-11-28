<?php

namespace Tempest\Testing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Provide
{
    public array $entries;

    public function __construct(
        /** $var string|array[] $entries */
        string|array ...$entries
    ) {
        $this->entries = $entries;
    }
}