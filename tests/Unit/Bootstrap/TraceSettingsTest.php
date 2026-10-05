<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Bootstrap;

use ampf\Bootstrap\TraceSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * What a log may hold of a failure: the calls of its stack trace and their arguments — all but a parameter that is marked
 * sensitive, which PHP never lists. PHP's settings are the process's: every test puts them back.
 */
#[CoversClass(TraceSettings::class)]
final class TraceSettingsTest extends TestCase
{
    private string|false $ignoreArgs;

    private string|false $paramMaxLength;

    private static function failingWith(
        #[SensitiveParameter]
        string $secret,
        string $note,
    ): Throwable {
        return new RuntimeException('failed with ' . strlen($secret) . ' characters: ' . $note);
    }

    public function testAnExceptionMadeAfterItListsTheArgumentsOfTheCallsAndKeepsOnlyTheMarkedParameterOut(): void
    {
        ini_set('zend.exception_ignore_args', '1');
        $withoutArguments = self::failingWith('the-password-that-was-typed', 'order-4711');

        self::assertArrayNotHasKey('args', $withoutArguments->getTrace()[0], 'a php.ini of the production kind');

        TraceSettings::apply();
        $listed = self::failingWith('the-password-that-was-typed', 'order-4711');

        self::assertSame('0', ini_get('zend.exception_ignore_args'));
        self::assertArrayHasKey('args', $listed->getTrace()[0]);
        self::assertStringContainsString(
            "failingWith(Object(SensitiveParameterValue), 'order-4711')",
            $listed->getTraceAsString(),
        );
        self::assertStringNotContainsString('the-password', (string)$listed);
    }

    protected function setUp(): void
    {
        $this->ignoreArgs = ini_get('zend.exception_ignore_args');
        $this->paramMaxLength = ini_get('zend.exception_string_param_max_len');
        // The length that PHP's own default lists of a text argument
        ini_set('zend.exception_string_param_max_len', '15');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', (string)$this->ignoreArgs);
        ini_set('zend.exception_string_param_max_len', (string)$this->paramMaxLength);
    }
}
