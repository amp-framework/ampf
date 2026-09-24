<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Bean\BeanFactory;
use ampf\Request\HttpRequest;
use ampf\Router\RouteResolver;
use ampf\Router\RouteResolverInterface;
use ampf\Tests\Support\RecordingHttpRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HttpRequest::class)]
final class HttpRequestTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function provideReferers(): array
    {
        return [
            'a page of this site' => ['https://app.example/articles?page=2', 'articles?page=2'],
            'the host in capitals' => ['https://APP.example/dashboard', 'dashboard'],
            'http on this host' => ['http://app.example/dashboard', 'dashboard'],
            'the default port named' => ['https://app.example:443/dashboard', 'dashboard'],
            'the site\'s root' => ['https://app.example/', null],
            'no path' => ['https://app.example', null],
            'another site' => ['https://evil.example/dashboard', null],
            'this site\'s address in another site\'s path' => ['https://evil.example/https://app.example/x', null],
            'this site\'s address in another site\'s query' => ['https://evil.example/?u=https://app.example/x', null],
            'a suffix of this host' => ['https://app.example.evil.example/dashboard', null],
            'this host as a user name' => ['https://app.example@evil.example/dashboard', null],
            'another port' => ['https://app.example:8443/dashboard', null],
            'another scheme' => ['javascript://app.example/%0Aalert(1)', null],
            'no scheme' => ['//app.example/dashboard', null],
            'a relative path' => ['/dashboard', null],
            'garbage' => ['http:///', null],
        ];
    }

    public function testACookieGetsSafeAttributesUnlessTheCallNamesOthers(): void
    {
        $request = new RecordingHttpRequest();
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

        $request->setCookieParam('theme', 'dark', 0, ['httponly' => false, 'samesite' => 'Strict']);
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
            $request = new RecordingHttpRequest(['HTTPS' => (string)$https]);
            $request->setCookieParam('a', 'b');
            self::assertSame($secure, $request->getSentCookies()[0]['options']['secure'], 'HTTPS=' . $https);
        }

        $request = new RecordingHttpRequest(['HTTPS' => 'on']);
        $request->setCookieParam('a', 'b', 0, ['secure' => false]);
        self::assertFalse($request->getSentCookies()[0]['options']['secure'], 'the call decides');
    }

    public function testTheConfigurationsCookiesBlockSetsTheApplicationsDefaults(): void
    {
        $request = new RecordingHttpRequest();
        $request->setBeanFactory(new BeanFactory([
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
        $request = new RecordingHttpRequest(['HTTPS' => 'on'], ['remember' => 'token']);
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
            $request = new RecordingHttpRequest();

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
        $request = new RecordingHttpRequest();
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
        $request = new RecordingHttpRequest();
        $request->setRawRedirect("/show/1\r\nX: y");

        $this->expectException(RuntimeException::class);
        $request->flush();
    }

    #[DataProvider('provideReferers')]
    public function testTheRefererCountsOnlyWhenItsOriginIsThisHost(string $referer, ?string $expected): void
    {
        $request = new RecordingHttpRequest(['HTTP_HOST' => 'app.example', 'HTTP_REFERER' => $referer]);

        self::assertSame($expected, $request->getRefererLocalized());
    }

    public function testTheRefererOnAHostWithAPortAndUnderASubdirectory(): void
    {
        $request = new RecordingHttpRequest([
            'HTTP_HOST' => 'localhost:8080',
            'HTTP_REFERER' => 'http://localhost:8080/app/articles?page=3',
            'SCRIPT_NAME' => '/app/index.php',
        ]);

        self::assertSame('articles?page=3', $request->getRefererLocalized());
    }

    public function testNoHostHeaderMeansNoLocalReferer(): void
    {
        foreach ([[], ['HTTP_HOST' => ''], ['HTTP_HOST' => ' ']] as $host) {
            $request = new RecordingHttpRequest($host + ['HTTP_REFERER' => 'https://app.example/dashboard']);

            self::assertNull($request->getRefererLocalized(), 'no exception either');
        }

        self::assertNull(new RecordingHttpRequest(['HTTP_HOST' => 'app.example'])->getRefererLocalized());
    }

    public function testSingleValuesReadAsStringsAndArraysAsAbsent(): void
    {
        $request = new RecordingHttpRequest(
            get: ['page' => '3', 'tags' => ['1', '2']],
            post: ['comment' => ' hi '],
        );

        self::assertSame('3', $request->getGetString('page'));
        self::assertSame('', $request->getGetString('tags'), 'a list where one value is expected');
        self::assertSame('', $request->getGetString('absent'));
        self::assertSame(' hi ', $request->getPostString('comment'), 'not trimmed: the call sites decide');
        self::assertSame('', $request->getPostString('page'), 'POST does not fall back to GET');
    }

    public function testParamStringPrefersTheFormOverTheQueryString(): void
    {
        $request = new RecordingHttpRequest(
            get: ['min_price' => '5', 'sort' => 'newest'],
            post: ['min_price' => '7'],
        );

        self::assertSame('7', $request->getParamString('min_price'));
        self::assertSame('newest', $request->getParamString('sort'));
        self::assertSame('', $request->getParamString('absent'));
    }

    public function testListsReadEveryStringEntryAndASingleValueAsOne(): void
    {
        $query = ['item_id' => ['4', ['nested'], '5']];

        self::assertSame(
            ['9'],
            new RecordingHttpRequest(get: $query, post: ['item_id' => '9'])->getParamStrings('item_id'),
        );
        self::assertSame(['4', '5'], new RecordingHttpRequest(get: $query)->getParamStrings('item_id'));
        self::assertSame([], new RecordingHttpRequest()->getParamStrings('item_id'));
    }

    public function testMapsKeepTheirKeysAndDropNonStringEntries(): void
    {
        $request = new RecordingHttpRequest(post: ['name' => ['12' => 'Alpha', 'new' => 'Beta', '7' => ['x']]]);

        self::assertSame([12 => 'Alpha', 'new' => 'Beta'], $request->getPostStringMap('name'));
        self::assertSame([], $request->getPostStringMap('absent'));
        self::assertSame([], new RecordingHttpRequest(post: ['name' => 'scalar'])->getPostStringMap('name'));
    }

    public function testARequestComesFromThisSiteUnlessTheBrowserSaysOtherwise(): void
    {
        foreach (['same-origin' => true, 'none' => true, 'Same-Origin' => true, '' => true] as $site => $expected) {
            $request = new RecordingHttpRequest(['HTTP_SEC_FETCH_SITE' => (string)$site]);
            self::assertSame($expected, $request->comesFromThisSite(), 'Sec-Fetch-Site: ' . $site);
        }

        self::assertTrue(new RecordingHttpRequest()->comesFromThisSite(), 'a browser that does not send the header');
        self::assertFalse(new RecordingHttpRequest(['HTTP_SEC_FETCH_SITE' => 'cross-site'])->comesFromThisSite());
        self::assertFalse(new RecordingHttpRequest(['HTTP_SEC_FETCH_SITE' => 'same-site'])->comesFromThisSite());
    }

    public function testTheBodyIsTheRawInput(): void
    {
        self::assertSame('{"key":"value"}', new RecordingHttpRequest(body: '{"key":"value"}')->getBody());
        self::assertSame('', new RecordingHttpRequest()->getBody(), 'the command line has no request body');
    }

    public function testALinkParameterMayBeAnyScalar(): void
    {
        $request = new RecordingHttpRequest();
        $request->setBeanFactory(new BeanFactory([
            'routes' => ['show' => ['controller' => 'ShowController', 'pattern' => 'show/(?P<id>[0-9]+)']],
            'beans' => [RouteResolverInterface::class => ['class' => RouteResolver::class]],
        ]));

        self::assertSame(
            '/show/42?page=2&public=1&hidden=&none=&ratio=1.5',
            $request->getActionLink(
                'show',
                ['id' => 42, 'page' => 2, 'public' => true, 'hidden' => false, 'none' => null, 'ratio' => 1.5],
            ),
        );

        $request->setRedirect('show', ['id' => 7], 303);
        self::assertTrue($request->isRedirect());
        self::assertSame(['code' => 303, 'target' => '/show/7'], $request->getRedirect());
    }
}
