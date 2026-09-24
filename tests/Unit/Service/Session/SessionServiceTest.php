<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\Session;

use ampf\Bean\BeanFactory;
use ampf\Service\Session\SessionService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideMalformedCookieAttributes(): iterable
    {
        yield 'a negative lifetime' => [['lifetime' => -1], 'lifetime'];
        yield 'a lifetime of text' => [['lifetime' => '3600'], 'lifetime'];
        yield 'a path that is no text' => [['path' => 1], 'path'];
        yield 'a domain that is no text' => [['domain' => null], 'domain'];
        yield 'secure as text' => [['secure' => 'yes'], 'secure'];
        yield 'httponly as a number' => [['httponly' => 1], 'httponly'];
        yield 'a SameSite of another name' => [['samesite' => 'lax'], 'samesite'];
        yield 'an unknown attribute' => [['expires' => 0], 'expires'];
    }

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
        $this->expectExceptionMessage('Failed to give the session a new id.');
        $session->regenerateId();
    }

    public function testDestroyEmptiesTheSessionAndEndsIt(): void
    {
        $session = $this->newSession([]);
        $session->setAttribute('user', 42);
        $id = (string)session_id();

        $session->destroy();

        self::assertSame([], $_SESSION);
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertFileDoesNotExist(session_save_path() . '/sess_' . $id);
    }

    public function testDestroyingAClosedSessionWithoutCookiesEmptiesWhatWasRead(): void
    {
        $session = $this->newSession([]);
        $session->setAttribute('user', 42);
        $session->close();
        ini_set('session.use_cookies', '0');

        $session->destroy();

        self::assertSame([], $_SESSION);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testAnAttributeIsRemovedWithItsValue(): void
    {
        $session = $this->newSession([]);
        $session->setAttribute('user', 42);
        $session->setAttribute('other', 'kept');

        $session->removeAttribute('user');
        $session->removeAttribute('missing');

        self::assertFalse($session->hasAttribute('user'));
        self::assertNull($session->getAttribute('user'));
        self::assertSame(['other' => 'kept'], $_SESSION);
    }

    public function testAnAttributeNeedsAName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A session attribute needs a name.');

        $this->newSession([])->setAttribute(' ', 42);
    }

    public function testASessionStartedElsewhereIsTakenAsItIs(): void
    {
        session_start();
        $params = session_get_cookie_params();
        $session = $this->newSession(['session' => ['cookie' => ['path' => '/elsewhere']]]);

        $session->setAttribute('user', 42);

        self::assertSame($params, session_get_cookie_params(), 'its cookie went out already');
        self::assertSame(42, $_SESSION['user']);
    }

    public function testTheConfigurationMaySwitchStrictModeOffButNotCookiesOnly(): void
    {
        ini_set('session.use_only_cookies', '1');
        $this->newSession(['session' => ['use_strict_mode' => false, 'use_only_cookies' => false]])->hasAttribute('a');

        self::assertSame('0', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.use_only_cookies'), 'PHP deprecated the other ways');
    }

    public function testEveryCookieAttributeIsTheConfigurations(): void
    {
        $cookie = [
            'lifetime' => 3_600,
            'path' => '/app',
            'domain' => 'app.example',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Strict',
        ];

        $this->newSession(['session' => ['cookie' => $cookie]])->hasAttribute('a');

        self::assertSame($cookie, array_intersect_key(session_get_cookie_params(), $cookie));
    }

    public function testASameSiteNoneCookieOverHttpsIsSecure(): void
    {
        $_SERVER['HTTPS'] = 'ON';
        $this->newSession(['session' => ['cookie' => ['samesite' => 'None']]])->hasAttribute('a');

        self::assertSame('None', session_get_cookie_params()['samesite']);
        self::assertTrue(session_get_cookie_params()['secure']);
    }

    public function testHttpsOffIsPlainHttp(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $this->newSession([])->hasAttribute('a');

        self::assertFalse(session_get_cookie_params()['secure']);
    }

    public function testASameSiteNoneCookieMustBeSecure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A SameSite=None cookie must be Secure.');

        $this->newSession(['session' => ['cookie' => ['samesite' => 'None', 'secure' => false]]])->hasAttribute('a');
    }

    /**
     * @param array<string, mixed> $cookie
     */
    #[DataProvider('provideMalformedCookieAttributes')]
    public function testAMalformedCookieAttributeIsRefused(array $cookie, string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The configuration\'s session.cookie has an unknown or malformed attribute ' . $name . '.',
        );

        $this->newSession(['session' => ['cookie' => $cookie]])->hasAttribute('a');
    }

    public function testACookieBlockThatIsNoArrayIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The configuration\'s session.cookie must be an array, not string.');

        $this->newSession(['session' => ['cookie' => 'secure']])->hasAttribute('a');
    }

    public function testASessionBlockThatIsNoArrayIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The configuration\'s session must be an array, not string.');

        $this->newSession(['session' => 'strict'])->hasAttribute('a');
    }

    public function testWithoutABeanFactoryTheDefaultsApply(): void
    {
        new SessionService()->setAttribute('user', 42);

        self::assertTrue(session_get_cookie_params()['httponly']);
        self::assertSame('1', ini_get('session.use_strict_mode'));
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
