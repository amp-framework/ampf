<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Helper;

use ampf\Helper\Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The registry is the process's: every test uses keys of its own.
 */
#[CoversClass(Registry::class)]
final class RegistryTest extends TestCase
{
    public function testAValueIsKeptForTheProcess(): void
    {
        self::assertFalse(Registry::has('registry-test-kept'));
        self::assertNull(Registry::get('registry-test-kept'));

        Registry::set('registry-test-kept', ['answer' => 42]);

        self::assertTrue(Registry::has('registry-test-kept'));
        self::assertSame(['answer' => 42], Registry::get('registry-test-kept'));
    }

    public function testALaterValueReplacesTheEarlierOne(): void
    {
        Registry::set('registry-test-replaced', 'first');
        Registry::set('registry-test-replaced', 'second');

        self::assertSame('second', Registry::get('registry-test-replaced'));
    }

    public function testANullValueCountsAsAbsent(): void
    {
        Registry::set('registry-test-null', 'something');
        Registry::set('registry-test-null', null);

        self::assertFalse(Registry::has('registry-test-null'));
        self::assertNull(Registry::get('registry-test-null'));
    }

    public function testFalsyValuesArePresent(): void
    {
        foreach (['registry-test-zero' => 0, 'registry-test-empty' => '', 'registry-test-false' => false] as $key => $value) {
            Registry::set($key, $value);

            self::assertTrue(Registry::has($key), $key);
            self::assertSame($value, Registry::get($key), $key);
        }
    }
}
