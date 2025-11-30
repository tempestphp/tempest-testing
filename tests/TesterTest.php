<?php

namespace Tempest\Testing\Tests;

use Exception;
use Tempest\DateTime\Exception\InvalidArgumentException;
use Tempest\Testing\Provide;
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

    #[Test, Provide(
        ['test' => 1, 'expected' => 1, 'succeeds' => true],
        ['test' => 1, 'expected' => 2, 'succeeds' => false],
        ['test' => 1, 'expected' => '1', 'succeeds' => true],
        ['test' => false, 'expected' => '', 'succeeds' => true],
    )]
    public function isEqualTo(mixed $test, mixed $expected, bool $succeeds): void
    {
        if ($succeeds) {
            test(fn () => test($test)->isEqualTo($expected))->succeeds();
        } else {
            test(fn () => test($test)->isEqualTo($expected))->fails();
        }
    }

    #[Test]
    public function isEqualToObject(): void
    {
        $a = (object) [];
        $b = (object) [];
        $c = (object) ['a' => 'a'];

        test(fn () => test($a)->isEqualTo($a))->succeeds();
        test(fn () => test($a)->isEqualTo($b))->succeeds();
        test(fn () => test($a)->isEqualTo($c))->fails();
    }

    #[Test, Provide(
        ['test' => 1, 'expected' => 1, 'succeeds' => false],
        ['test' => 1, 'expected' => 2, 'succeeds' => true],
        ['test' => 1, 'expected' => '1', 'succeeds' => false],
        ['test' => false, 'expected' => '', 'succeeds' => false],
    )]
    public function isNotEqualTo(mixed $test, mixed $expected, bool $succeeds): void
    {
        if ($succeeds) {
            test(fn () => test($test)->isNotEqualTo($expected))->succeeds();
        } else {
            test(fn () => test($test)->isNotEqualTo($expected))->fails();
        }
    }

    #[Test]
    public function isNotEqualToObject(): void
    {
        $a = (object) [];
        $b = (object) [];
        $c = (object) ['a' => 'a'];

        test(fn () => test($a)->isNotEqualTo($a))->fails();
        test(fn () => test($a)->isNotEqualTo($b))->fails();
        test(fn () => test($a)->isNotEqualTo($c))->succeeds();
    }

    #[Test]
    public function isCallable(): void
    {
        test(fn () => test(fn () => true)->isCallable())->succeeds();
        test(fn () => test('a')->isCallable())->fails();
    }

    #[Test]
    public function hasCount(): void
    {
        test(fn () => test([1, 2, 3])->hasCount(3))->succeeds();
        test(fn () => test([1, 2, 3])->hasCount(4))->fails();
    }

    #[Test]
    public function hasNotCount(): void
    {
        test(fn () => test([1, 2, 3])->hasNotCount(3))->fails();
        test(fn () => test([1, 2, 3])->hasNotCount(4))->succeeds();
    }

    #[Test]
    public function contains(): void
    {
        test(fn () => test([1, 2, 3])->contains(2))->succeeds();
        test(fn () => test([1, 2, 3])->contains(4))->fails();
    }

    #[Test]
    public function containsNot(): void
    {
        test(fn () => test([1, 2, 3])->containsNot(2))->fails();
        test(fn () => test([1, 2, 3])->containsNot(4))->succeeds();
    }

    #[Test]
    public function hasKey(): void
    {
        test(fn () => test([1, 2, 3])->hasKey(2))->succeeds();
        test(fn () => test([1, 2, 3])->hasKey(4))->fails();
    }

    #[Test]
    public function missesKey(): void
    {
        test(fn () => test([1, 2, 3])->missesKey(2))->fails();
        test(fn () => test([1, 2, 3])->missesKey(4))->succeeds();
    }

    #[Test]
    public function instanceOf(): void
    {
        test(fn () => test($this)->instanceOf(self::class))->succeeds();
        test(fn () => test('')->instanceOf(self::class))->fails();
    }

    #[Test]
    public function notInstanceOf(): void
    {
        test(fn () => test($this)->notInstanceOf(self::class))->fails();
        test(fn () => test('')->notInstanceOf(self::class))->succeeds();
    }

    #[Test]
    public function exceptionThrown(): void
    {
        test(function () {
            test(fn () => throw new Exception())->exceptionThrown(Exception::class);
        })->succeeds();

        test(function () {
            test(fn () => throw new InvalidArgumentException())->exceptionThrown(Exception::class);
        })->succeeds();

        test(function () {
            test(fn () => throw new Exception())->exceptionThrown(InvalidArgumentException::class);
        })->fails();

        test(function () {
            test()->exceptionThrown(InvalidArgumentException::class);
        })->fails();
    }

    #[Test]
    public function exceptionNotThrown(): void
    {
        test(function () {
            test(fn () => throw new Exception())->exceptionNotThrown(Exception::class);
        })->fails();

        test(function () {
            test(fn () => throw new InvalidArgumentException())->exceptionNotThrown(Exception::class);
        })->fails();

        test(function () {
            test(fn () => throw new Exception())->exceptionNotThrown(InvalidArgumentException::class);
        })->succeeds();

        test(function () {
            test()->exceptionNotThrown(InvalidArgumentException::class);
        })->succeeds();
    }
}