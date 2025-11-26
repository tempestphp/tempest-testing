<?php

namespace Tempest\Testing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Provide
{
    public array $entries;

    public function __construct(
        /** $var array[] $entries */
        array ...$entries
    ) {
        $this->entries = $entries;
    }
}