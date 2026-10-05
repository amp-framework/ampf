<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Request\HttpRequest;
use ampf\Tests\Support\PostLimitHttpRequest;
use ampf\Tests\Support\RecordingHttpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A request whose body PHP dropped for being bigger than `post_max_size`: its form and its files are empty, and nothing
 * else says it was not sent empty.
 */
#[CoversClass(HttpRequest::class)]
final class HttpRequestPostSizeTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, int, bool}> the announced length, the limit and whether the request is too large
     */
    public static function provideLengths(): iterable
    {
        yield 'no length announced' => [null, 1_000, false];
        yield 'a length below the limit' => ['999', 1_000, false];
        yield 'a length that is the limit' => ['1000', 1_000, false];
        yield 'a length one above the limit' => ['1001', 1_000, true];
        yield 'a length far above it' => ['99999999999999999999', 1_000, true];
        yield 'a length with blanks before it' => [' 2000', 1_000, true];
        yield 'a length that is no number' => ['many', 1_000, false];
        yield 'a length that is empty' => ['', 1_000, false];
        yield 'a length below nothing' => ['-5', 1_000, false];
        yield 'no limit at all' => ['99999999999', 0, false];
        yield 'a limit of one and a length of two' => ['2', 1, true];
    }

    #[DataProvider('provideLengths')]
    public function testARequestIsTooLargeWhenItsAnnouncedLengthIsAboveTheLimit(
        ?string $length,
        int $limit,
        bool $tooLarge,
    ): void {
        $request = new PostLimitHttpRequest($limit, $length === null ? [] : ['CONTENT_LENGTH' => $length]);

        self::assertSame($tooLarge, $request->isPostTooLarge());
    }

    public function testTheLimitIsPhpsPostMaxSizeUnlessAnApplicationsRequestSaysOtherwise(): void
    {
        $limit = ini_parse_quantity((string)ini_get('post_max_size'));
        self::assertGreaterThan(0, $limit, 'This test needs a post_max_size that is a limit.');

        self::assertFalse(new RecordingHttpRequest(['CONTENT_LENGTH' => (string)$limit])->isPostTooLarge());
        self::assertTrue(new RecordingHttpRequest(['CONTENT_LENGTH' => (string)($limit + 1)])->isPostTooLarge());
    }
}
