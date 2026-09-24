<?php

declare(strict_types=1);

namespace ampfTest\Services;

use ampf\beans\impl\DefaultBeanFactory;
use ampf\services\session\impl\DefaultSessionService;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * PHP's real session, each test in a process of its own (a session starts once per process, before any output).
 * The settings start from PHP's own defaults, which production's php.ini leaves as they are: no strict mode, no
 * HttpOnly, no SameSite.
 *
 * @internal
 *
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 *
 * @covers \ampf\services\session\impl\DefaultSessionService
 */
#[RunTestsInSeparateProcesses]
final class DefaultSessionServiceTest extends TestCase
{
    public function testTheCookieParametersAndTheIniSettingsAreInPlaceWhenTheSessionStarts(): void
    {
        $session = $this->newSession(['session' => ['cookie' => ['secure' => true]]]);

        self::assertSame(PHP_SESSION_NONE, session_status(), 'nothing starts before the first use');
        $session->setAttribute('user', 42);

        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        $expected = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        self::assertSame($expected, array_intersect_key(session_get_cookie_params(), $expected));
        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.use_only_cookies'));
        self::assertSame(42, $session->getAttribute('user'));
    }

    public function testWithoutASettingTheCookieIsSecureExactlyWhenTheRequestCameOverHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $this->newSession([])->hasAttribute('anything');

        self::assertTrue(session_get_cookie_params()['secure']);
        self::assertTrue(session_get_cookie_params()['httponly']);
        self::assertSame('Lax', session_get_cookie_params()['samesite']);
    }

    public function testOverPlainHttpTheCookieIsNotSecure(): void
    {
        unset($_SERVER['HTTPS']);
        $this->newSession([])->hasAttribute('anything');

        self::assertFalse(session_get_cookie_params()['secure']);
    }

    public function testAnIdTheServerNeverIssuedIsReplaced(): void
    {
        session_id('attackerchosen0123456789abcdefgh');
        $this->newSession([])->setAttribute('user', 42);

        self::assertNotSame('attackerchosen0123456789abcdefgh', session_id());
    }

    public function testCloseWritesTheSessionAndReleasesIt(): void
    {
        $session = $this->newSession([]);
        $session->setAttribute('user', 42);
        $id = session_id();
        $session->close();

        self::assertSame(PHP_SESSION_NONE, session_status(), 'the lock is released');
        self::assertSame(42, $session->getAttribute('user'), 'what was read stays readable');

        $session->setAttribute('later', 'lost');
        session_id((string)$id);
        session_start(['read_and_close' => true]);
        self::assertSame(['user' => 42], $_SESSION, 'written before the close, nothing after it');
    }

    public function testASessionClosedBeforeItsFirstUseIsReadWithoutKeepingTheLock(): void
    {
        $session = $this->newSession([]);
        $session->close();

        self::assertNull($session->getAttribute('user'));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    protected function setUp(): void
    {
        $directory = sys_get_temp_dir() . '/ampf-session-test-' . getmypid();

        if (!is_dir($directory)) {
            mkdir($directory, 0o700);
        }

        ini_set('session.save_path', $directory);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function newSession(array $config): DefaultSessionService
    {
        $session = new DefaultSessionService();
        $session->setBeanFactory(new DefaultBeanFactory($config));

        return $session;
    }
}
