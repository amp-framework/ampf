<?php

declare(strict_types=1);

namespace ampf\Tests\Integration;

use ampf\Tests\Support\BuiltInServer;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The fixture application's stylesheet behind PHP's built-in web server: the address a page writes into a link element
 * carries the version of the file, and the web server — not the application — answers it with the file.
 */
#[CoversNothing]
final class AssetLinkServerTest extends TestCase
{
    private static BuiltInServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new BuiltInServer(dirname(__DIR__) . '/Fixtures/App/public');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testTheAddressOfTheStylesheetCarriesTheVersionOfItsFileAndTheWebServerAnswersIt(): void
    {
        $file = dirname(__DIR__) . '/Fixtures/App/public/assets/css/app.css';
        $content = (string)file_get_contents($file);

        $address = self::$server->request('GET', '/stylesheet');

        self::assertSame(200, $address['status']);
        self::assertSame('/assets/css/app.css?v=' . substr(hash('sha256', $content), 0, 16), $address['body']);

        $asset = self::$server->request('GET', $address['body']);

        self::assertSame(200, $asset['status']);
        self::assertSame($content, $asset['body']);
        self::assertStringStartsWith('text/css', self::$server->headers($asset, 'Content-Type')[0] ?? '');
        self::assertSame(
            [],
            self::$server->headers($asset, 'Set-Cookie'),
            'The web server starts no session for a file.',
        );
    }
}
