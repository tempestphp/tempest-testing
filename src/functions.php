<?php

namespace Tempest\Testing
{

    use Tempest\Testing\Testers\PrimitiveTester;

    function test(mixed $subject = null): PrimitiveTester
    {
        return new PrimitiveTester($subject);
    }
}