<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Helper;

use ampf\Helper\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(Functions::class)]
final class FunctionsTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideValuesThatAreNoStringKeyedArray(): iterable
    {
        yield 'null' => [null, 'Expected an array keyed by strings, got null.'];
        yield 'a string' => ['beans', 'Expected an array keyed by strings, got string.'];
        yield 'an object' => [new stdClass(), 'Expected an array keyed by strings, got stdClass.'];
        yield 'a list' => [['a', 'b'], 'Expected an array keyed by strings, found the key 0.'];
        yield 'a map with one int key' => [['a' => 1, 7 => 2], 'Expected an array keyed by strings, found the key 7.'];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideStringKeyedArrays(): iterable
    {
        yield 'an empty array' => [[]];
        yield 'a map' => [['beans' => [], 'routes' => null, '' => 1]];
    }

    #[DataProvider('provideStringKeyedArrays')]
    public function testAnArrayKeyedByStringsPasses(mixed $array): void
    {
        Functions::assertStringMixedArray($array);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('provideValuesThatAreNoStringKeyedArray')]
    public function testAnythingElseIsRefused(mixed $value, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        Functions::assertStringMixedArray($value);
    }

    public function testTheInputKeepsItsArraysAndGetsItsScalarsAsText(): void
    {
        self::assertNull(Functions::cleanGPCSLists(null));
        self::assertSame([], Functions::cleanGPCSLists([]));
        self::assertSame(
            ['page' => '2', 'ratio' => '1.5', 'on' => '1', 'off' => '', 'ids' => ['4', ['nested']], 0 => 'first'],
            Functions::cleanGPCSLists(
                ['page' => 2, 'ratio' => 1.5, 'on' => true, 'off' => false, 'ids' => ['4', ['nested']], 0 => 'first'],
            ),
        );
    }

    public function testAnInputValueThatIsNeitherScalarNorArrayIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The input argv is neither a scalar nor an array, but null.');

        Functions::cleanGPCSLists(['page' => '1', 'argv' => null]);
    }

    public function testAScalarBecomesItsText(): void
    {
        self::assertSame('text', Functions::convertToString('text'));
        self::assertSame('42', Functions::convertToString(42));
        self::assertSame('-0.5', Functions::convertToString(-0.5));
        self::assertSame('1', Functions::convertToString(true));
        self::assertSame('', Functions::convertToString(false));
    }

    public function testAnythingButAScalarHasNoText(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected a scalar, got array.');

        Functions::convertToString(['text']);
    }

    public function testAJsonArrayOfStringsIsDecodedAsAList(): void
    {
        self::assertSame(['a', 'b', ''], Functions::decodeJSONArray('["a", "b", ""]'));
        self::assertSame([], Functions::decodeJSONArray('[]'));
    }

    public function testWhatIsNoJsonArrayIsAnEmptyList(): void
    {
        foreach (['', 'null', '"a"', '42', '{"a": "b"}', '[unclosed'] as $json) {
            self::assertSame([], Functions::decodeJSONArray($json), $json);
        }
    }

    public function testAJsonArrayWithAnotherValueIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected a JSON array of strings, found int.');

        Functions::decodeJSONArray('["a", 2]');
    }
}
