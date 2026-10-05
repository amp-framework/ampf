<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\Session;

use ampf\Bean\BeanFactory;
use ampf\Request\CliRequest;
use ampf\Service\Session\SessionService;
use ampf\Tests\Support\RecordingHttpRequest;
use ampf\Tests\Support\SeamSessionService;
use ampf\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * PHP's real session with `session.lazy` on, each test in a process of its own: a request that brings no session cookie
 * has nothing to read, and reading starts none — a visitor's page would otherwise leave a session file behind for every
 * request. Only what is written starts one. With the setting off, which is how the framework was, a read starts the
 * session as ever.
 *
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
#[CoversClass(SessionService::class)]
#[RunTestsInSeparateProcesses]
final class SessionServiceLazyTest extends TestCase
{
    private TemporaryDirectory $directory;

    /**
     * @return iterable<string, array{string}> a value of the session's cookie that cannot be a session id
     */
    public static function provideValuesThatAreNoSessionId(): iterable
    {
        yield 'nothing' => [''];
        yield 'a single character' => ['x'];
        yield 'one character short of the shortest id' => [str_repeat('a', 21)];
        yield 'one character beyond the longest id' => [str_repeat('a', 257)];
        yield 'a path' => ['../../../../etc/passwd/aaaaaaaaaaaaaaaaaaaaaa'];
        yield 'a blank in the middle' => [str_repeat('a', 11) . ' ' . str_repeat('a', 11)];
        yield 'an underscore' => [str_repeat('a', 11) . '_' . str_repeat('a', 11)];
        yield 'a semicolon' => [str_repeat('a', 11) . ';' . str_repeat('a', 11)];
        yield 'a letter of another script' => [str_repeat('a', 20) . 'ä' . str_repeat('a', 5)];
        yield 'a null byte' => [str_repeat('a', 11) . "\0" . str_repeat('a', 11)];
        yield 'a character that is none before the id' => ['!' . str_repeat('a', 30)];
        yield 'a character that is none after the id' => [str_repeat('a', 30) . '!'];
        yield 'a line feed after the id' => [str_repeat('a', 30) . "\n"];
    }

    /**
     * @return iterable<string, array{string}> a value of the session's cookie that can be a session id, which the
     *                                         server did not issue
     */
    public static function provideValuesThatMayBeASessionId(): iterable
    {
        $alphabet = implode('', [...range('a', 'z'), ...range('A', 'Z'), ...range(0, 9), ',', '-']);

        yield 'the shortest id' => [str_repeat('a', 22)];
        yield 'the longest id' => [str_repeat('Z', 256)];
        yield 'the whole alphabet and the two signs' => [$alphabet];
        yield 'digits only' => [str_repeat('7', 40)];
        yield 'an id of 32 characters, as PHP makes them' => ['0123456789abcdef0123456789abcdef'];
        yield 'signs only' => [str_repeat(',-', 12)];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}> a `session` block that does not switch the setting on
     */
    public static function provideBlocksThatAreNotLazy(): iterable
    {
        yield 'no block' => [[]];
        yield 'a block without the setting' => [['session' => ['use_strict_mode' => true]]];
        yield 'the setting off' => [['session' => ['lazy' => false]]];
    }

    public function testAReadStartsNoSessionWhenTheRequestBringsNoCookieOfOne(): void
    {
        $this->storedSession(['user' => 42]);
        $session = $this->session(['another-cookie' => 'is no session']);

        self::assertNull($session->getAttribute('user'));
        self::assertFalse($session->hasAttribute('user'));
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame(0, $session->getStarts());
    }

    public function testAReadStartsTheSessionOfTheCookieTheRequestBrings(): void
    {
        $id = $this->storedSession(['user' => 42]);
        $session = $this->session([$this->sessionName() => $id]);

        self::assertSame(42, $session->getAttribute('user'));
        self::assertTrue($session->hasAttribute('user'));
        self::assertFalse($session->hasAttribute('other'));
        self::assertNull($session->getAttribute('other'));
        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame(1, $session->getStarts(), 'It is started once for the request.');
    }

    #[DataProvider('provideValuesThatAreNoSessionId')]
    public function testACookieWhoseValueCannotBeASessionIdIsNoCookieOfASession(string $value): void
    {
        $session = $this->session([$this->sessionName() => $value]);

        self::assertNull($session->getAttribute('user'));
        self::assertFalse($session->hasAttribute('user'));
        self::assertSame(PHP_SESSION_NONE, session_status(), 'a forged id costs no file and no cookie');
        self::assertSame(0, $session->getStarts());
    }

    #[DataProvider('provideValuesThatMayBeASessionId')]
    public function testACookieWhoseValueMayBeASessionIdStartsTheSessionOfIt(string $value): void
    {
        $session = $this->session([$this->sessionName() => $value]);

        self::assertNull($session->getAttribute('user'), 'the server issued no such id: strict mode gives a new one');
        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame(1, $session->getStarts());
    }

    public function testACookieThatIsAnArrayIsNoCookieOfASessionEither(): void
    {
        // The cookie `PHPSESSID[]=x` is an array in PHP, whatever the double's PHPDoc says of cookies
        // @phpstan-ignore argument.type (a cookie that is an array, on purpose)
        $request = new RecordingHttpRequest(cookie: [$this->sessionName() => ['x']]);
        $session = $this->sessionOf($request);

        self::assertNull($session->getAttribute('user'));
        self::assertFalse($session->hasAttribute('user'));
        self::assertSame(0, $session->getStarts());
    }

    public function testASessionAskedForWhetherItHoldsSomethingIsStartedByTheCookieToo(): void
    {
        $id = $this->storedSession(['user' => 42]);
        $session = $this->session([$this->sessionName() => $id]);

        self::assertTrue($session->hasAttribute('user'));
        self::assertSame(1, $session->getStarts());
    }

    public function testTheCookieIsTheOneTheSessionIsNamed(): void
    {
        session_name('app_session');
        $id = $this->storedSession(['user' => 42]);

        $withTheDefaultName = $this->session(['PHPSESSID' => $id]);
        self::assertNull($withTheDefaultName->getAttribute('user'));
        self::assertSame(0, $withTheDefaultName->getStarts());

        $named = $this->session(['app_session' => $id]);
        self::assertSame(42, $named->getAttribute('user'));
        self::assertSame(1, $named->getStarts());
    }

    public function testAWriteStartsASessionOnceAndWhatItWroteIsThereToRead(): void
    {
        $session = $this->session([]);

        self::assertNull($session->getAttribute('written'));
        self::assertSame(0, $session->getStarts());

        $session->setAttribute('written', 'in this request');

        self::assertSame(1, $session->getStarts());
        self::assertSame('in this request', $session->getAttribute('written'));
        self::assertTrue($session->hasAttribute('written'));
        self::assertFalse($session->hasAttribute('other'));
        self::assertSame(1, $session->getStarts(), 'It is started once for the request.');
    }

    public function testASessionStartedElsewhereIsReadWhateverTheRequestBrought(): void
    {
        session_start();
        $_SESSION['elsewhere'] = 'started by somebody else';
        $session = $this->session([]);

        self::assertSame('started by somebody else', $session->getAttribute('elsewhere'));
        self::assertTrue($session->hasAttribute('elsewhere'));
        self::assertSame(0, $session->getStarts(), 'It was not the service that started it.');
    }

    public function testASessionClosedAfterAWriteIsReadAsItWas(): void
    {
        $session = $this->session([]);
        $session->setAttribute('user', 42);
        $session->close();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame(42, $session->getAttribute('user'));
        self::assertTrue($session->hasAttribute('user'));
        self::assertSame(1, $session->getStarts());
    }

    public function testASessionClosedBeforeItsUseStartsNoneForAReadWithoutACookie(): void
    {
        $this->storedSession(['user' => 42]);
        $session = $this->session([]);
        $session->close();

        self::assertNull($session->getAttribute('user'));
        self::assertFalse($session->hasAttribute('user'));
        self::assertSame(0, $session->getStarts());
    }

    public function testASessionClosedBeforeItsUseIsReadAndClosedAtOnceWhenTheCookieIsThere(): void
    {
        $id = $this->storedSession(['user' => 42]);
        $session = $this->session([$this->sessionName() => $id]);
        $session->close();

        self::assertSame(42, $session->getAttribute('user'));
        self::assertSame(PHP_SESSION_NONE, session_status(), 'the lock is not kept');
        self::assertSame(1, $session->getStarts());
    }

    public function testACommandHasNoSessionToRead(): void
    {
        $this->storedSession(['user' => 42]);
        $session = $this->sessionOf(new CliRequest(['bin/index.php']));

        self::assertNull($session->getAttribute('user'));
        self::assertFalse($session->hasAttribute('user'));
        self::assertSame(0, $session->getStarts());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideBlocksThatAreNotLazy')]
    public function testWithTheSettingOffAReadStartsTheSessionAsItAlwaysDid(array $config): void
    {
        $beanFactory = new BeanFactory($config);
        $beanFactory->set('Request', new RecordingHttpRequest(cookie: []));
        $session = new SeamSessionService();
        $session->setBeanFactory($beanFactory);

        self::assertNull($session->getAttribute('user'));
        self::assertSame(1, $session->getStarts(), 'A request without a cookie starts a session by reading it.');
        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertFalse($session->hasAttribute('user'));
        self::assertSame(1, $session->getStarts(), 'It is started once for the request.');
    }

    public function testWithoutABeanFactoryAReadStartsTheSession(): void
    {
        $session = new SeamSessionService();

        self::assertNull($session->getAttribute('user'));
        self::assertSame(1, $session->getStarts());
    }

    public function testTheSettingIsABooleanOrTheReadIsRefused(): void
    {
        $session = new SeamSessionService();
        $session->setBeanFactory(new BeanFactory(['session' => ['lazy' => 'yes']]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The configuration\'s session.lazy must be true or false, not string.');

        $session->getAttribute('user');
    }

    public function testWhetherThereIsAnythingToReadIsADecisionASubclassMayMake(): void
    {
        $id = $this->storedSession(['user' => 42]);

        $nothing = $this->session([$this->sessionName() => $id]);
        $nothing->decideNothingToRead(true);
        self::assertNull($nothing->getAttribute('user'));
        self::assertFalse($nothing->hasAttribute('user'));
        self::assertSame(0, $nothing->getStarts());
        self::assertSame(['hasNothingToRead'], $nothing->getCalledMethods());

        $something = $this->session([]);
        $something->decideNothingToRead(false);
        self::assertSame(42, $something->getAttribute('user'), 'the id PHP holds is the stored session\'s');
        self::assertSame(1, $something->getStarts());
        self::assertContains('hasNothingToRead', $something->getCalledMethods());
        self::assertContains('openSession', $something->getCalledMethods());
    }

    public function testTheSettingIsReadThroughAProtectedMethodASubclassMayChange(): void
    {
        $session = $this->session([]);

        self::assertNull($session->getAttribute('user'));
        self::assertContains('isLazy', $session->getCalledMethods());
    }

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory('ampf-lazy-session-test');
        ini_set('session.save_path', $this->directory->getPath());
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->directory->remove();
    }

    private function sessionName(): string
    {
        return (string)session_name();
    }

    /**
     * The lazy session of a request that brings the cookies.
     *
     * @param array<string, string> $cookies
     */
    private function session(array $cookies): SeamSessionService
    {
        return $this->sessionOf(new RecordingHttpRequest(cookie: $cookies));
    }

    /** The lazy session of the request. */
    private function sessionOf(object $request): SeamSessionService
    {
        $beanFactory = new BeanFactory(['session' => ['lazy' => true]]);
        $beanFactory->set('Request', $request);
        $session = new SeamSessionService();
        $session->setBeanFactory($beanFactory);

        return $session;
    }

    /**
     * A session an earlier request stored: its id is the one the next start takes up, its data is on the disk only.
     *
     * @param array<string, mixed> $data
     */
    private function storedSession(array $data): string
    {
        session_start();
        $_SESSION = $data;
        $id = (string)session_id();
        session_write_close();
        $_SESSION = [];

        return $id;
    }
}
