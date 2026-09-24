<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\Session;

use ampf\Bean\BeanFactory;
use ampf\Service\Session\SessionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * PHP's real session, each test in a process of its own (a session starts once per process, before any output).
 * The settings start from PHP's own defaults, which a stock php.ini leaves as they are: no strict mode, no
 * HttpOnly, no SameSite.
 *
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
#[CoversClass(SessionService::class)]
#[RunTestsInSeparateProcesses]
final class SessionServiceTest extends TestCase
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

    public function testANewIdKeepsTheDataAndEndsTheOldSession(): void
    {
        $session = $this->newSession([]);
        $session->setAttribute('user', 42);
        $before = (string)session_id();

        $session->regenerateId();

        self::assertNotSame($before, session_id());
        self::assertSame(42, $session->getAttribute('user'));
        self::assertFileDoesNotExist(session_save_path() . '/sess_' . $before, 'an id known before is worth nothing');
    }

    public function testARenewedSessionIsEmptyUnderANewIdAndStaysOpen(): void
    {
        $session = $this->newSession([]);
        $session->setAttribute('user', 42);
        $before = (string)session_id();

        $session->renew();
        $session->setAttribute('message', 'logged out');

        self::assertNotSame($before, session_id());
        self::assertNull($session->getAttribute('user'));
        self::assertSame(PHP_SESSION_ACTIVE, session_status());

        $id = (string)session_id();
        session_write_close();
        session_id($id);
        session_start(['read_and_close' => true]);
        self::assertSame(['message' => 'logged out'], $_SESSION, 'what was written after the renewal is kept');
    }

    public function testAClosedSessionGetsNoNewId(): void
    {
        $session = $this->newSession([]);
        $session->setAttribute('user', 42);
        $session->close();

        $this->expectException(RuntimeException::class);
        $session->regenerateId();
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
    private function newSession(array $config): SessionService
    {
        $session = new SessionService();
        $session->setBeanFactory(new BeanFactory($config));

        return $session;
    }
}
