<?php

namespace Tempest\Testing\Tests;

use Tempest\Testing\Test;
use function Tempest\Testing\test;

final class TesterTest
{
    #[Test]
    public function fail(): void
    {
        test(fn () => test()->fail())->fails();
    }

    #[Test]
    public function succeed(): void
    {
        test(fn () => test()->succeed())->succeeds();
    }

    #[Test]
    public function is(): void
    {
        test(fn () => test(1)->is(1))->succeeds();
        test(fn () => test(1)->is(2))->fails();
        test(fn () => test(1)->is('1'))->fails();
        test(fn () => test(0)->is(''))->fails();

        $a = (object) [];
        $b = (object) [];
        $c = (object) ['a' => 'a'];

        test(fn () => test($a)->is($a))->succeeds();
        test(fn () => test($a)->is($b))->fails();
        test(fn () => test($a)->is($c))->fails();
    }

    #[Test]
    public function isNot(): void
    {
        test(fn () => test(1)->isNot(1))->fails();
        test(fn () => test(1)->isNot(2))->succeeds();
        test(fn () => test(1)->isNot('1'))->succeeds();
        test(fn () => test(0)->isNot(''))->succeeds();

        $a = (object) [];
        $b = (object) [];
        $c = (object) ['a' => 'a'];

        test(fn () => test($a)->isNot($a))->fails();
        test(fn () => test($a)->isNot($b))->succeeds();
        test(fn () => test($a)->isNot($c))->succeeds();
    }

    #[Test]
    public function isEqualTo(): void
    {
        test(fn () => test(1)->isEqualTo(1))->succeeds();
        test(fn () => test(1)->isEqualTo(2))->fails();
        test(fn () => test(1)->isEqualTo('1'))->succeeds();
        test(fn () => test(false)->isEqualTo(''))->succeeds();

        $a = (object) [];
        $b = (object) [];
        $c = (object) ['a' => 'a'];

        test(fn () => test($a)->isEqualTo($a))->succeeds();
        test(fn () => test($a)->isEqualTo($b))->succeeds();
        test(fn () => test($a)->isEqualTo($c))->fails();
    }

    #[Test]
    public function isNotEqualTo(): void
    {
        test(fn () => test(1)->isNotEqualTo(1))->fails();
        test(fn () => test(1)->isNotEqualTo(2))->succeeds();
        test(fn () => test(1)->isNotEqualTo('1'))->fails();
        test(fn () => test(false)->isNotEqualTo(''))->fails();

        $a = (object) [];
        $b = (object) [];
        $c = (object) ['a' => 'a'];

        test(fn () => test($a)->isNotEqualTo($a))->fails();
        test(fn () => test($a)->isNotEqualTo($b))->fails();
        test(fn () => test($a)->isNotEqualTo($c))->succeeds();
    }
}