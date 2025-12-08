<?php

namespace Tempest\Testing\Tests;

use Exception;
use Tempest\DateTime\Exception\InvalidArgumentException;
use Tempest\Testing\Provide;
use Tempest\Testing\Test;
use function Tempest\Testing\test;

final class PrimitiveTesterTest
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
        test(fn () => test(1)->is(2))->fails('`1` was not expected `2`');
        test(fn () => test(1)->is('1'))->fails("`1` was not expected `'1'`");
        test(fn () => test(0)->is(''))->fails();

        $a = (object) [];
        $b = (object) [];
        $c = (object) ['a' => 'a'];

        test(fn () => test($a)->is($a))->succeeds();
        test(fn () => test($a)->is($b))->fails('`stdClass` was not expected `stdClass`');
        test(fn () => test($a)->is($c))->fails('`stdClass` was not expected `stdClass`');

        test(fn () => test(1)->is('1', 'it %s', 'failed'))->fails("it `'failed'`");
    }

    #[Test]
    public function isNot(): void
    {
        test(fn () => test(1)->isNot(1))->fails('`1` was expected not to be `1`');
        test(fn () => test(1)->isNot(2))->succeeds();
        test(fn () => test(1)->isNot('1'))->succeeds();
        test(fn () => test(0)->isNot(''))->succeeds();

        $a = (object) [];
        $b = (object) [];
        $c = (object) ['a' => 'a'];

        test(fn () => test($a)->isNot($a))->fails('`stdClass` was expected not to be `stdClass`');
        test(fn () => test($a)->isNot($b))->succeeds();
        test(fn () => test($a)->isNot($c))->succeeds();
        test(fn () => test(1)->isNot(1, 'custom %s', 'reason'))->fails("custom `'reason'`");
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

    public function isEqualToWithFailMessage(): void
    {
        test(fn () => test(1)->isEqualTo('2', 'custom %s', 'reason'))->fails("custom `'reason'`");
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
    public function isNotEqualToWithFailMessage(): void
    {
        test(fn () => test(1)->isNotEqualTo('1', 'custom %s', 'reason'))->fails("custom `'reason'`");
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
        test(fn () => test('a')->isCallable())->fails("`'a'` was not callable");
        test(fn () => test('a')->isCallable('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotCallable(): void
    {
        test(fn () => test(fn () => true)->isNotCallable())->fails('`Closure` was callable while it should not');
        test(fn () => test('not_callable')->isNotCallable())->succeeds();
        test(fn () => test(fn () => true)->isNotCallable('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function hasCount(): void
    {
        test(fn () => test([1, 2, 3])->hasCount(3))->succeeds();
        test(fn () => test([1, 2, 3])->hasCount(4))->fails('`array` did not have expected `4` items');
        test(fn () => test(1)->hasCount(4))->fails('`1` was not countable');
        test(fn () => test([1, 2, 3])->hasCount(4, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function hasNotCount(): void
    {
        test(fn () => test([1, 2, 3])->hasNotCount(3))->fails('`array` had `3` items while it should not');
        test(fn () => test([1, 2, 3])->hasNotCount(4))->succeeds();
        test(fn () => test(1)->hasNotCount(4))->fails('`1` was not countable');
        test(fn () => test([1, 2, 3])->hasNotCount(3, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function contains(): void
    {
        test(fn () => test([1, 2, 3])->contains(2))->succeeds();
        test(fn () => test([1, 2, 3])->contains(4))->fails('`array` did not contain expected `4`');
        test(fn () => test('abc')->contains('b'))->succeeds();
        test(fn () => test('abc')->contains('d'))->fails("`'abc'` did not contain expected `'d'`");
        test(fn () => test(1)->contains('d'))->fails('to check contains, the test subject must be a string or an array; instead got `1`');
        test(fn () => test([1, 2, 3])->contains(4, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function containsNot(): void
    {
        test(fn () => test([1, 2, 3])->containsNot(2))->fails('`array` contained `2` while it should not');
        test(fn () => test([1, 2, 3])->containsNot(4))->succeeds();
        test(fn () => test('abc')->containsNot('b'))->fails("`'abc'` contained `'b'` while it should not");
        test(fn () => test('abc')->containsNot('d'))->succeeds();
        test(fn () => test(1)->containsNot('d'))->fails('to check contains, the test subject must be a string or an array; instead got `1`');
        test(fn () => test([1, 2, 3])->containsNot(2, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function hasKey(): void
    {
        test(fn () => test([1, 2, 3])->hasKey(2))->succeeds();
        test(fn () => test([1, 2, 3])->hasKey(4))->fails('`array` did not have key `4`');
        test(fn () => test(1)->hasKey(4))->fails('`1` was not array');
        test(fn () => test([1, 2, 3])->hasKey(4, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function missesKey(): void
    {
        test(fn () => test([1, 2, 3])->missesKey(2))->fails('`array` had key `2` while it should not');
        test(fn () => test([1, 2, 3])->missesKey(4))->succeeds();
        test(fn () => test(1)->missesKey(4))->fails('`1` was not array');
        test(fn () => test([1, 2, 3])->missesKey(2, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function instanceOf(): void
    {
        test(fn () => test($this)->instanceOf(self::class))->succeeds();
        test(fn () => test('')->instanceOf(self::class))->fails("`''` was not an instance of `'Tempest\\\\Testing\\\\Tests\\\\PrimitiveTesterTest'`");
        test(fn () => test('')->instanceOf(self::class, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function notInstanceOf(): void
    {
        test(fn () => test($this)->isNotInstanceOf(self::class))->fails("`Tempest\\Testing\\Tests\\PrimitiveTesterTest` was an instance of `'Tempest\\\\Testing\\\\Tests\\\\PrimitiveTesterTest'` while it should not");
        test(fn () => test('')->isNotInstanceOf(self::class))->succeeds();
        test(fn () => test($this)->isNotInstanceOf(self::class, 'custom %s', 'reason'))->fails("custom `'reason'`");
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
        })->fails("expected exception `'Tempest\\\\DateTime\\\\Exception\\\\InvalidArgumentException'` was not thrown, instead got `'Exception'`");

        test(function () {
            test(fn () => true)->exceptionThrown(InvalidArgumentException::class);
        })->fails("expected exception `'Tempest\\\\DateTime\\\\Exception\\\\InvalidArgumentException'` was not thrown");

        test(function () {
            test()->exceptionThrown(InvalidArgumentException::class);
        })->fails('to test exceptions, the test subject must be a callable; instead got `NULL`');

        test(function () {
            test(fn () => true)->exceptionThrown(Exception::class, null, 'custom %s', 'reason');
        })->fails("custom `'reason'`");
    }

    #[Test]
    public function exceptionNotThrown(): void
    {
        test(function () {
            test(fn () => throw new Exception())->exceptionNotThrown(Exception::class);
        })->fails("exception `'Exception'` was thrown while it should not");

        test(function () {
            test(fn () => throw new InvalidArgumentException())->exceptionNotThrown(Exception::class);
        })->fails("exception `'Tempest\\\\DateTime\\\\Exception\\\\InvalidArgumentException'` was thrown while it should not");

        test(function () {
            test(fn () => throw new Exception())->exceptionNotThrown(InvalidArgumentException::class);
        })->succeeds();

        test(function () {
            test()->exceptionNotThrown(InvalidArgumentException::class);
        })->succeeds();

        test(function () {
            test(fn () => throw new Exception())->exceptionNotThrown(Exception::class, 'custom %s', 'reason');
        })->fails("custom `'reason'`");
    }

    #[Test]
    public function isCountable(): void
    {
        test(fn () => test([1, 2])->isCountable())->succeeds();
        test(fn () => test('a')->isCountable())->fails("`'a'` was not countable");
        test(fn () => test('a')->isCountable('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotCountable(): void
    {
        test(fn () => test([1, 2])->isNotCountable())->fails('`array` was countable while it should not');
        test(fn () => test('a')->isNotCountable())->succeeds();
        test(fn () => test([1, 2])->isNotCountable('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function startsWith(): void
    {
        test(fn () => test('abc')->startsWith('ab'))->succeeds();
        test(fn () => test('abc')->startsWith('zz'))->fails("`'abc'` did not start with `'zz'`");
        test(fn () => test(1)->startsWith('zz'))->fails('`1` was not string');
        test(fn () => test('abc')->startsWith('zz', 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function endsWith(): void
    {
        test(fn () => test('abc')->endsWith('bc'))->succeeds();
        test(fn () => test('abc')->endsWith('zz'))->fails("`'abc'` did not end with `'zz'`");
        test(fn () => test(1)->endsWith('zz'))->fails('`1` was not string');
        test(fn () => test('abc')->endsWith('zz', 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function startsNotWith(): void
    {
        test(fn () => test('abc')->startsNotWith('ab'))->fails("`'abc'` started with `'ab'` while it should not");
        test(fn () => test('abc')->startsNotWith('zz'))->succeeds();
        test(fn () => test(1)->startsNotWith('zz'))->fails('`1` was not string');
        test(fn () => test('abc')->startsNotWith('ab', 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function endsNotWith(): void
    {
        test(fn () => test('abc')->endsNotWith('bc'))->fails("`'abc'` ended with `'bc'` while it should not");
        test(fn () => test('abc')->endsNotWith('zz'))->succeeds();
        test(fn () => test(1)->endsNotWith('zz'))->fails('`1` was not string');
        test(fn () => test('abc')->endsNotWith('bc', 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isList(): void
    {
        test(fn () => test([1, 2, 3])->isList())->succeeds();
        test(fn () => test([1 => 'a'])->isList())->fails('`array` was not a list');
        test(fn () => test('a')->isList())->fails('`\'a\'` was not array');
        test(fn () => test([1 => 'a'])->isList('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotList(): void
    {
        test(fn () => test([1, 2, 3])->isNotList())->fails('`array` was a list while it should not');
        test(fn () => test([1 => 'a'])->isNotList())->succeeds();
        test(fn () => test('a')->isNotList())->fails('`\'a\'` was not array');
        test(fn () => test([1, 2, 3])->isNotList('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isEmpty(): void
    {
        test(fn () => test([])->isEmpty())->succeeds();
        test(fn () => test('a')->isEmpty())->fails("`'a'` was not empty");
        test(fn () => test('a')->isEmpty('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotEmpty(): void
    {
        test(fn () => test('a')->isNotEmpty())->succeeds();
        test(fn () => test('')->isNotEmpty())->fails("`''` was empty while it should not");
        test(fn () => test('')->isNotEmpty('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function greaterThan(): void
    {
        test(fn () => test(5)->greaterThan(4))->succeeds();
        test(fn () => test(5)->greaterThan(5))->fails('`5` was not greater than `5`');
        test(fn () => test('a')->greaterThan(4))->fails('`\'a\'` was not numeric');
        test(fn () => test(5)->greaterThan(5, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function greaterThanOrEqual(): void
    {
        test(fn () => test(5)->greaterThanOrEqual(5))->succeeds();
        test(fn () => test(4)->greaterThanOrEqual(5))->fails('`4` was not greater than or equal to `5`');
        test(fn () => test('a')->greaterThanOrEqual(4))->fails('`\'a\'` was not numeric');
        test(fn () => test(4)->greaterThanOrEqual(5, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function lessThan(): void
    {
        test(fn () => test(4)->lessThan(5))->succeeds();
        test(fn () => test(5)->lessThan(5))->fails('`5` was not less than `5`');
        test(fn () => test('a')->lessThan(4))->fails('`\'a\'` was not numeric');
        test(fn () => test(5)->lessThan(5, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function lessThanOrEqual(): void
    {
        test(fn () => test(5)->lessThanOrEqual(5))->succeeds();
        test(fn () => test(6)->lessThanOrEqual(5))->fails('`6` was not less than or equal to `5`');
        test(fn () => test('a')->lessThanOrEqual(4))->fails('`\'a\'` was not numeric');
        test(fn () => test(6)->lessThanOrEqual(5, 'custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isTrue(): void
    {
        test(fn () => test(true)->isTrue())->succeeds();
        test(fn () => test(false)->isTrue())->fails('`false` was not true');
        test(fn () => test(false)->isTrue('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isFalse(): void
    {
        test(fn () => test(false)->isFalse())->succeeds();
        test(fn () => test(true)->isFalse())->fails('`true` was not false');
        test(fn () => test(true)->isFalse('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isTrueish(): void
    {
        test(fn () => test(1)->isTrueish())->succeeds();
        test(fn () => test(0)->isTrueish())->fails('`0` was not trueish');
        test(fn () => test(0)->isTrueish('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isFalseish(): void
    {
        test(fn () => test(0)->isFalseish())->succeeds();
        test(fn () => test(1)->isFalseish())->fails('`1` was not falseish');
        test(fn () => test(1)->isFalseish('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNull(): void
    {
        test(fn () => test(null)->isNull())->succeeds();
        test(fn () => test(0)->isNull())->fails('`0` was not null');
        test(fn () => test(0)->isNull('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotNull(): void
    {
        test(fn () => test(0)->isNotNull())->succeeds();
        test(fn () => test(null)->isNotNull())->fails('`NULL` was null while it should not');
        test(fn () => test(null)->isNotNull('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isArray(): void
    {
        test(fn () => test([1])->isArray())->succeeds();
        test(fn () => test(1)->isArray())->fails('`1` was not array');
        test(fn () => test(1)->isArray('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotArray(): void
    {
        test(fn () => test([1])->isNotArray())->fails('`array` was array while it should not');
        test(fn () => test(1)->isNotArray())->succeeds();
        test(fn () => test([1])->isNotArray('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isBool(): void
    {
        test(fn () => test(true)->isBool())->succeeds();
        test(fn () => test(1)->isBool())->fails('`1` was not bool');
        test(fn () => test(1)->isBool('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotBool(): void
    {
        test(fn () => test(true)->isNotBool())->fails('`true` was bool while it should not');
        test(fn () => test(1)->isNotBool())->succeeds();
        test(fn () => test(true)->isNotBool('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isFloat(): void
    {
        test(fn () => test(1.2)->isFloat())->succeeds();
        test(fn () => test(1)->isFloat())->fails('`1` was not float');
        test(fn () => test(1)->isFloat('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotFloat(): void
    {
        test(fn () => test(1.2)->isNotFloat())->fails('`1.2` was float while it should not');
        test(fn () => test(1)->isNotFloat())->succeeds();
        test(fn () => test(1.2)->isNotFloat('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isInt(): void
    {
        test(fn () => test(1)->isInt())->succeeds();
        test(fn () => test(1.1)->isInt())->fails('`1.1` was not int');
        test(fn () => test(1.1)->isInt('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotInt(): void
    {
        test(fn () => test(1)->isNotInt())->fails('`1` was int while it should not');
        test(fn () => test(1.1)->isNotInt())->succeeds();
        test(fn () => test(1)->isNotInt('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNumeric(): void
    {
        test(fn () => test('1')->isNumeric())->succeeds();
        test(fn () => test('a')->isNumeric())->fails("`'a'` was not numeric");
        test(fn () => test('a')->isNumeric('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotNumeric(): void
    {
        test(fn () => test('1')->isNotNumeric())->fails("`'1'` was numeric while it should not");
        test(fn () => test('a')->isNotNumeric())->succeeds();
        test(fn () => test('1')->isNotNumeric('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isObject(): void
    {
        test(fn () => test((object) [])->isObject())->succeeds();
        test(fn () => test(1)->isObject())->fails('`1` was not object');
        test(fn () => test(1)->isObject('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotObject(): void
    {
        test(fn () => test((object) [])->isNotObject())->fails('`stdClass` was object while it should not');
        test(fn () => test(1)->isNotObject())->succeeds();
        test(fn () => test((object) [])->isNotObject('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isResource(): void
    {
        $res = fopen('php://temp', 'r');
        test(fn () => test($res)->isResource())->succeeds();
        fclose($res);

        test(fn () => test(1)->isResource())->fails('`1` was not resource');
        test(fn () => test(1)->isResource('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotResource(): void
    {
        $res = fopen('php://temp', 'r');
        test(fn () => test($res)->isNotResource())->fails('`resource` was resource while it should not');
        test(fn () => test(1)->isNotResource())->succeeds();
        test(fn () => test($res)->isNotResource('custom %s', 'reason'))->fails("custom `'reason'`");
        fclose($res);
    }

    #[Test]
    public function isString(): void
    {
        test(fn () => test('a')->isString())->succeeds();
        test(fn () => test(1)->isString())->fails('`1` was not string');
        test(fn () => test(1)->isString('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotString(): void
    {
        test(fn () => test('a')->isNotString())->fails("`'a'` was string while it should not");
        test(fn () => test(1)->isNotString())->succeeds();
        test(fn () => test('a')->isNotString('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isScalar(): void
    {
        test(fn () => test(1)->isScalar())->succeeds();
        test(fn () => test([])->isScalar())->fails('`array` was not scalar');
        test(fn () => test([])->isScalar('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotScalar(): void
    {
        test(fn () => test(1)->isNotScalar())->fails('`1` was scalar while it should not');
        test(fn () => test([])->isNotScalar())->succeeds();
        test(fn () => test(1)->isNotScalar('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isIterable(): void
    {
        test(fn () => test([1])->isIterable())->succeeds();
        test(fn () => test(1)->isIterable())->fails('`1` was not iterable');
        test(fn () => test(1)->isIterable('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotIterable(): void
    {
        test(fn () => test([1])->isNotIterable())->fails('`array` was iterable while it should not');
        test(fn () => test(1)->isNotIterable())->succeeds();
        test(fn () => test([1])->isNotIterable('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isJson(): void
    {
        test(fn () => test('{"a":1}')->isJson())->succeeds();
        test(fn () => test('not json')->isJson())->fails("`'not json'` was not valid JSON");
        test(fn () => test(1)->isJson())->fails('`1` was not string');
        test(fn () => test('not json')->isJson('custom %s', 'reason'))->fails("custom `'reason'`");
    }

    #[Test]
    public function isNotJson(): void
    {
        test(fn () => test('{"a":1}')->isNotJson())->fails("`'{\"a\":1}'` was valid JSON while it should not");
        test(fn () => test('not json')->isNotJson())->succeeds();
        test(fn () => test(1)->isNotJson())->succeeds();
        test(fn () => test('{"a":1}')->isNotJson('custom %s', 'reason'))->fails("custom `'reason'`");
    }
}