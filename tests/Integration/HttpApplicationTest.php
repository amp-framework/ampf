<?php

declare(strict_types=1);

namespace ampf\Tests\Integration;

use ampf\Bootstrap\DoctrineConfiguration;
use ampf\Tests\Fixtures\App\Doctrine\Entity\NoteEntity;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The fixture application behind PHP's built-in web server, over HTTP on the loopback: routing, templates, headers,
 * cookies, the session, the request token, redirects and the database, as a browser meets them.
 */
#[CoversNothing]
final class HttpApplicationTest extends TestCase
{
    /**
     * @var resource|null
     */
    private static $server;

    private static string $address = '';

    private static string $database = '';

    private static string $log = '';

    public static function setUpBeforeClass(): void
    {
        self::$database = (string)tempnam(sys_get_temp_dir(), 'ampf-app-database-');
        self::$log = (string)tempnam(sys_get_temp_dir(), 'ampf-app-server-');
        self::createSchema();

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        self::$address = (string)stream_socket_get_name($socket, false);
        fclose($socket);

        $server = proc_open(
            [PHP_BINARY, '-S', self::$address, '-t', dirname(__DIR__) . '/Fixtures/App/public'],
            [1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a']],
            $pipes,
            null,
            [...getenv(), 'AMPF_FIXTURE_DATABASE' => self::$database],
        );
        self::assertIsResource($server);
        self::$server = $server;

        // The server listens once it is up
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @stream_socket_client('tcp://' . self::$address, $errorCode, $errorMessage, 0.1);

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(50_000);
        }

        throw new RuntimeException('The built-in server did not start: ' . file_get_contents(self::$log));
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }

        unlink(self::$database);
        unlink(self::$log);
    }

    private static function createSchema(): void
    {
        $entityManager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$database]),
            DoctrineConfiguration::create([dirname(__DIR__) . '/Fixtures/App/Doctrine/Entity']),
        );
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(NoteEntity::class)]);
        $entityManager->getConnection()->close();
    }

    public function testTheHomePageComesThroughTheWholeStack(): void
    {
        $response = $this->request('GET', '/');

        self::assertSame(200, $response['status']);
        self::assertSame('text/html; charset=UTF-8', $this->header($response, 'Content-Type'));
        self::assertStringStartsWith('no-store', (string)$this->header($response, 'Cache-Control'));
        self::assertNull($this->header($response, 'X-Powered-By'), 'PHP does not name itself');
        self::assertStringContainsString('<title>Home</title>', $response['body']);
        self::assertStringContainsString('<h1>Welcome, world!</h1>', $response['body']);
        self::assertStringContainsString('<a href="/hello/ada">Ada</a>', $response['body']);
        self::assertStringContainsString('<a href="/notes">Notes</a>', $response['body']);
    }

    public function testARoutesCapturesReachTheControllerByName(): void
    {
        self::assertSame('<p>Hello ada</p>', $this->request('GET', '/hello/ada')['body']);
        self::assertSame(
            '<p>Hello ada</p>',
            $this->request('GET', '/hello/ada?x=1')['body'],
            'the query is no part of the route',
        );
    }

    public function testARouteNoPatternMatchesIsTheApplicationsToAnswer(): void
    {
        $response = $this->request('GET', '/hello/Ada');

        self::assertSame(404, $response['status']);
        self::assertSame('Not found', $response['body']);
    }

    public function testTheSessionKeepsItsDataUnderItsCookie(): void
    {
        $first = $this->request('GET', '/counter');
        $cookie = (string)$this->header($first, 'Set-Cookie');

        self::assertSame('Visits: 1', $first['body']);
        self::assertMatchesRegularExpression('/^PHPSESSID=[^;]+; path=\/; HttpOnly; SameSite=Lax$/', $cookie);

        $session = explode(';', $cookie)[0];
        self::assertSame('Visits: 2', $this->request('GET', '/counter', ['Cookie: ' . $session])['body']);
        self::assertSame('Visits: 1', $this->request('GET', '/counter')['body'], 'another browser, another session');
    }

    public function testACookieAndARedirectGoOutTogether(): void
    {
        $response = $this->request('GET', '/theme/dark');

        self::assertSame(302, $response['status']);
        self::assertSame('/', $this->header($response, 'Location'));
        self::assertSame('theme=dark; path=/; HttpOnly; SameSite=Lax', $this->header($response, 'Set-Cookie'));
        self::assertSame('', $response['body']);
    }

    public function testAFormWithTheRequestsTokenStoresItsNote(): void
    {
        $page = $this->request('GET', '/notes');
        $session = explode(';', (string)$this->header($page, 'Set-Cookie'))[0];
        self::assertSame(1, preg_match('/action="(\/notes\?stkn=[0-9a-f]{32})"/', $page['body'], $action));

        $posted = $this->request(
            'POST',
            html_entity_decode($action[1]),
            ['Cookie: ' . $session, 'Content-Type: application/x-www-form-urlencoded'],
            'text=' . rawurlencode('<b>Buy milk</b>'),
        );

        self::assertSame(303, $posted['status']);
        self::assertSame('/notes', $this->header($posted, 'Location'));
        self::assertStringContainsString(
            '<li>&lt;b&gt;Buy milk&lt;/b&gt; (' . gmdate('Y-m-d') . ')</li>',
            $this->request('GET', '/notes', ['Cookie: ' . $session])['body'],
        );

        $again = $this->request(
            'POST',
            html_entity_decode($action[1]),
            ['Cookie: ' . $session, 'Content-Type: application/x-www-form-urlencoded'],
            'text=twice',
        );
        self::assertSame(403, $again['status'], 'a token is accepted once');
    }

    public function testAFormWithoutTheTokenIsForbidden(): void
    {
        $response = $this->request(
            'POST',
            '/notes',
            ['Content-Type: application/x-www-form-urlencoded'],
            'text=forged',
        );

        self::assertSame(403, $response['status']);
        self::assertSame('Forbidden', $response['body']);
        self::assertStringNotContainsString('forged', $this->request('GET', '/notes')['body']);
    }

    /**
     * @param list<string> $headers
     *
     * @return array{status: int, headers: list<string>, body: string}
     */
    private function request(string $method, string $path, array $headers = [], ?string $body = null): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $body ?? '',
            'follow_location' => 0,
            'ignore_errors' => true,
        ]]);
        $content = file_get_contents('http://' . self::$address . $path, false, $context);
        $lines = http_get_last_response_headers() ?? [];
        self::assertIsString($content, (string)file_get_contents(self::$log));
        self::assertSame(1, preg_match('/^HTTP\/1\.[01] (\d{3})/', $lines[0] ?? '', $status));

        return ['status' => (int)$status[1], 'headers' => array_slice($lines, 1), 'body' => $content];
    }

    /**
     * The value of the response's header of the name (its last one), null without one.
     *
     * @param array{status: int, headers: list<string>, body: string} $response
     */
    private function header(array $response, string $name): ?string
    {
        $value = null;

        foreach ($response['headers'] as $line) {
            [$lineName, $lineValue] = explode(':', $line, 2) + ['', ''];

            if (strcasecmp($lineName, $name) === 0) {
                $value = trim($lineValue);
            }
        }

        return $value;
    }
}
