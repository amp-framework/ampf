<?php

declare(strict_types=1);

namespace ampfTest\Requests;

use ampf\beans\impl\DefaultBeanFactory;
use ampfTest\Support\RecordingHttp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @covers \ampf\requests\impl\DefaultHttp
 */
final class DefaultHttpTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function provideReferers(): array
    {
        return [
            'a page of this site' => ['https://ogmem.test/cr/my?page=2', 'cr/my?page=2'],
            'the host in capitals' => ['https://OGMEM.test/dashboard', 'dashboard'],
            'http on this host' => ['http://ogmem.test/dashboard', 'dashboard'],
            'the default port named' => ['https://ogmem.test:443/dashboard', 'dashboard'],
            'the site\'s root' => ['https://ogmem.test/', null],
            'no path' => ['https://ogmem.test', null],
            'another site' => ['https://evil.example/dashboard', null],
            'this site\'s address in another site\'s path' => ['https://evil.example/https://ogmem.test/x', null],
            'this site\'s address in another site\'s query' => ['https://evil.example/?u=https://ogmem.test/x', null],
            'a suffix of this host' => ['https://ogmem.test.evil.example/dashboard', null],
            'this host as a user name' => ['https://ogmem.test@evil.example/dashboard', null],
            'another port' => ['https://ogmem.test:8443/dashboard', null],
            'another scheme' => ['javascript://ogmem.test/%0Aalert(1)', null],
            'no scheme' => ['//ogmem.test/dashboard', null],
            'a relative path' => ['/dashboard', null],
            'garbage' => ['http:///', null],
        ];
    }

    public function testACookieGetsSafeAttributesUnlessTheCallNamesOthers(): void
    {
        $request = new RecordingHttp();
        $request->setCookieParam('remember', 'token', 1_900_000_000);
        $cookies = $request->getSentCookies();

        self::assertSame(
            [[
                'name' => 'remember',
                'value' => 'token',
                'options' => [
                    'expires' => 1_900_000_000,
                    'path' => '/',
                    'domain' => '',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ],
            ]],
            $cookies,
        );
        self::assertSame('token', $request->getCookieParam('remember'), 'visible to the request at once');

        $request->setCookieParam('layout', 'classic', 0, ['httponly' => false, 'samesite' => 'Strict']);
        $cookies = $request->getSentCookies();
        self::assertCount(2, $cookies);
        self::assertSame(
            ['expires' => 0, 'path' => '/', 'domain' => '', 'secure' => false, 'httponly' => false, 'samesite' => 'Strict'],
            $cookies[1]['options'],
        );
    }

    public function testACookieIsSecureExactlyWhenTheRequestCameOverHttps(): void
    {
        foreach (['on' => true, '1' => true, 'off' => false, 'OFF' => false, '' => false] as $https => $secure) {
            $request = new RecordingHttp(['HTTPS' => (string)$https]);
            $request->setCookieParam('a', 'b');
            self::assertSame($secure, $request->getSentCookies()[0]['options']['secure'], 'HTTPS=' . $https);
        }

        $request = new RecordingHttp(['HTTPS' => 'on']);
        $request->setCookieParam('a', 'b', 0, ['secure' => false]);
        self::assertFalse($request->getSentCookies()[0]['options']['secure'], 'the call decides');
    }

    public function testTheConfigurationsCookiesBlockSetsTheApplicationsDefaults(): void
    {
        $request = new RecordingHttp();
        $request->setBeanFactory(new DefaultBeanFactory([
            'cookies' => ['secure' => true, 'samesite' => 'Strict', 'path' => '/app'],
        ]));

        $request->setCookieParam('a', 'b');
        $request->setCookieParam('c', 'd', 0, ['samesite' => 'Lax']);

        $cookies = $request->getSentCookies();
        self::assertCount(2, $cookies);
        self::assertSame(
            ['expires' => 0, 'path' => '/app', 'domain' => '', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict'],
            $cookies[0]['options'],
        );
        self::assertSame('Lax', $cookies[1]['options']['samesite']);
    }

    public function testADeletedCookieCarriesItsAttributes(): void
    {
        $request = new RecordingHttp(['HTTPS' => 'on'], ['remember' => 'token']);
        $request->destroyCookieParam('remember');
        $request->destroyCookieParam('absent');

        self::assertSame(
            [[
                'name' => 'remember',
                'value' => '',
                'options' => [
                    'expires' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ],
            ]],
            $request->getSentCookies(),
            'an empty value is PHP\'s deletion; a cookie the request does not carry is left alone',
        );
        self::assertFalse($request->hasCookieParam('remember'));
    }

    public function testAMalformedCookieAttributeIsRefused(): void
    {
        foreach (
            [
                ['samesite' => 'lax-ish'],
                ['secure' => 'yes'],
                ['httponly' => 1],
                ['path' => "/\r\nX: y"],
                ['expires' => 5],
                ['samesite' => 'None', 'secure' => false],
            ] as $options
        ) {
            $request = new RecordingHttp();

            try {
                $request->setCookieParam('a', 'b', 0, $options);
                self::fail('accepted ' . json_encode($options, JSON_THROW_ON_ERROR));
            } catch (InvalidArgumentException) {
                self::assertSame([], $request->getSentCookies());
            }
        }
    }

    public function testAHeaderWithAControlCharacterOrABadNameIsRefused(): void
    {
        $request = new RecordingHttp();
        $request->addHeader('X-Tab', "a\tb");

        foreach (
            [
                ['X-Test', "value\r\nSet-Cookie: x=y"],
                ['X-Test', "value\nX: y"],
                ['X-Test', "value\0"],
                ['X-Test', "value\x7F"],
                ["X-Test\r\nX", 'value'],
                ['X Test', 'value'],
                ['X-Test:', 'value'],
            ] as [$name, $value]
        ) {
            try {
                $request->addHeader($name, $value);
                self::fail('accepted ' . json_encode([$name, $value], JSON_THROW_ON_ERROR));
            } catch (RuntimeException) {
                self::assertStringEndsWith("X-Tab: a\tb", implode("\n", $request->getHeaders()));
            }
        }
    }

    public function testARedirectTargetWithAControlCharacterIsNeverSent(): void
    {
        $request = new RecordingHttp();
        $request->setRawRedirect("/show/1\r\nX: y");

        $this->expectException(RuntimeException::class);
        $request->flush();
    }

    #[DataProvider('provideReferers')]
    public function testTheRefererCountsOnlyWhenItsOriginIsThisHost(string $referer, ?string $expected): void
    {
        $request = new RecordingHttp(['HTTP_HOST' => 'ogmem.test', 'HTTP_REFERER' => $referer]);

        self::assertSame($expected, $request->getRefererLocalized());
    }

    public function testTheRefererOnAHostWithAPortAndUnderASubdirectory(): void
    {
        $request = new RecordingHttp([
            'HTTP_HOST' => 'localhost:29414',
            'HTTP_REFERER' => 'http://localhost:29414/app/cr/archive?page=3',
            'SCRIPT_NAME' => '/app/index.php',
        ]);

        self::assertSame('cr/archive?page=3', $request->getRefererLocalized());
    }

    public function testNoHostHeaderMeansNoLocalReferer(): void
    {
        foreach ([[], ['HTTP_HOST' => ''], ['HTTP_HOST' => ' ']] as $host) {
            $request = new RecordingHttp($host + ['HTTP_REFERER' => 'https://ogmem.test/dashboard']);

            self::assertNull($request->getRefererLocalized(), 'no exception either');
        }

        self::assertNull(new RecordingHttp(['HTTP_HOST' => 'ogmem.test'])->getRefererLocalized());
    }
}
