<?php

declare(strict_types=1);

namespace ampf\Tests\Integration;

use ampf\Tests\Support\BuiltInServer;
use ampf\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The fixture application that opts into the lazy session, behind PHP's built-in web server: a visitor's page sets no
 * cookie and leaves no session file, and neither does a cookie that cannot be a session's.
 */
#[CoversNothing]
final class OptInHttpApplicationTest extends TestCase
{
    private static BuiltInServer $server;

    private static TemporaryDirectory $sessions;

    public static function setUpBeforeClass(): void
    {
        self::$sessions = new TemporaryDirectory('ampf-opt-in-sessions');
        self::$server = new BuiltInServer(
            dirname(__DIR__) . '/Fixtures/OptInApp/public',
            ['session.save_path' => self::$sessions->getPath()],
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
        self::$sessions->remove();
    }

    public function testAVisitorsPageSetsNoCookieAndLeavesNoSessionFile(): void
    {
        $before = $this->sessionFiles();

        $response = self::$server->request('GET', '/welcome');

        self::assertSame(200, $response['status']);
        self::assertSame("<p>Welcome, visitor</p>\n", $response['body']);
        self::assertSame([], self::$server->headers($response, 'Set-Cookie'));
        self::assertSame($before, $this->sessionFiles());
    }

    public function testACookieThatCannotBeASessionIdCostsNoSessionFileAndNoCookie(): void
    {
        $before = $this->sessionFiles();

        $response = self::$server->request('GET', '/welcome', ['Cookie: PHPSESSID=forged']);

        self::assertSame(200, $response['status']);
        self::assertSame("<p>Welcome, visitor</p>\n", $response['body']);
        self::assertSame([], self::$server->headers($response, 'Set-Cookie'));
        self::assertSame($before, $this->sessionFiles());
    }

    /**
     * The session files the server wrote, by name.
     *
     * @return list<string>
     */
    private function sessionFiles(): array
    {
        $files = array_map('basename', glob(self::$sessions->getPath() . '/sess_*') ?: []);
        sort($files);

        return $files;
    }
}
