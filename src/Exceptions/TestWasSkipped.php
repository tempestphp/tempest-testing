<?php

namespace Tempest\Testing\Exceptions;

use Exception;

final class TestWasSkipped extends Exception implements TestException
{
    public function __construct(
        public readonly ?string $reason = null,
    ) {}
}
