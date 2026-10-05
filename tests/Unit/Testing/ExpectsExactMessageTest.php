<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing;

use ampf\Testing\ExpectsExactMessage;
use ampf\Tests\Support\ExactMessageTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use RuntimeException;

/**
 * The trait in the base of this test case, as an application has it: the exact message passes through PHPUnit's
 * runner, and a message that only contains it fails PHPUnit's own check of a thrown exception
 * (ExceptionExpectation::verify(), which the runner calls) in the expectation of another instance of this test.
 */
#[CoversTrait(ExpectsExactMessage::class)]
final class ExpectsExactMessageTest extends ExactMessageTestCase
{
    /** A message with what a regular expression would take for its own: a slash, a dot, brackets, a dollar. */
    private const string MESSAGE = 'The file a/b.php (the [first] one) costs $5 + 1.';

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMessagesThatOnlyContainIt(): iterable
    {
        yield 'something before it' => ['Oh. ' . self::MESSAGE];
        yield 'something after it' => [self::MESSAGE . ' Oh.'];
        yield 'a line feed after it' => [self::MESSAGE . "\n"];
        yield 'a part of it' => [substr(self::MESSAGE, 0, -1)];
    }

    public function testTheExactMessagePasses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageExactly(self::MESSAGE);

        throw new RuntimeException(self::MESSAGE);
    }

    public function testPhpunitsCheckTakesTheExactMessage(): void
    {
        $this->expectationOf(self::MESSAGE)->verify(new RuntimeException(self::MESSAGE));

        self::assertSame(1, self::getCount(), 'the message checked once');
    }

    #[DataProvider('provideMessagesThatOnlyContainIt')]
    public function testAMessageThatOnlyContainsItFails(string $message): void
    {
        $expectation = $this->expectationOf(self::MESSAGE);

        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessageExactly(
            'Failed asserting that exception message \'' . $message . '\' matches \'/\A'
            . preg_quote(self::MESSAGE, '/') . '\z/\'.',
        );

        $expectation->verify(new RuntimeException($message));
    }
}
