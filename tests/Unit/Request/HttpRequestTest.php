<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Bean\BeanFactory;
use ampf\Request\HttpRequest;
use ampf\Router\RouteResolver;
use ampf\Router\RouteResolverInterface;
use ampf\Service\XsrfToken\XsrfTokenService;
use ampf\Tests\Support\ArraySessionService;
use ampf\Tests\Support\RecordingHttpRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

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

    /**
     * @return iterable<string, array{string, list<array{string, float}>}>
     */
    public static function provideAcceptLanguages(): iterable
    {
        yield 'none' => ['', []];
        yield 'one language' => ['de', [['de', 1.0]]];
        yield 'languages in their order' => ['de-DE, en;q=0.8, *;q=0.1', [['de-DE', 1.0], ['en', 0.8], ['*', 0.1]]];
        yield 'the weight\'s forms' => [
            'a;q=1, b;q=1.0, c;q=1.000, d;q=0.5, e;q=0.50, f;q=0.125, g;Q=0.9, h; q=0.7',
            [['a', 1.0], ['b', 1.0], ['c', 1.0], ['d', 0.5], ['e', 0.5], ['f', 0.125], ['g', 0.9], ['h', 0.7]],
        ];
        yield 'not acceptable' => ['de;q=0, en;q=0.000, fr', [['fr', 1.0]]];
        yield 'malformed weights' => [
            'a;q=1.5, b;q=2, c;q=0.1234, d;q=, e;q=abc, f;level=1, g;, h;q = 0.5, i;q=-0.5, j',
            [['j', 1.0]],
        ];
        yield 'further parameters' => ['de;q=0.5;x=y', [['de', 0.5]]];
        yield 'empty ranges' => [' , ;q=0.5,,en', [['en', 1.0]]];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'the root' => ['/', '/index.php', ''];
        yield 'a page' => ['/articles', '/index.php', 'articles'];
        yield 'a page with a query' => ['/articles?page=2&sort=new', '/index.php', 'articles'];
        yield 'doubled slashes' => ['//articles/42', '/index.php', 'articles/42'];
        yield 'under a base path' => ['/app/articles/42', '/app/index.php', 'articles/42'];
        yield 'the base path itself' => ['/app', '/app/index.php', ''];
        yield 'the base path with a slash' => ['/app/', '/app/index.php', ''];
        yield 'the base path with a query' => ['/app?page=2', '/app/index.php', ''];
        yield 'a segment that only starts like the base path' => ['/application/x', '/app/index.php', 'application/x'];
        yield 'a deeper base path' => ['/web/app/articles', '/web/app/index.php', 'articles'];
        yield 'another directory' => ['/other/articles', '/app/index.php', 'other/articles'];
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

    public function testACookieTakesTheDomainTheCallNames(): void
    {
        $request = new RecordingHttpRequest();
        $request->setCookieParam('a', 'b', 0, ['domain' => 'app.example']);

        self::assertSame('app.example', $request->getSentCookies()[0]['options']['domain']);
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
                ['domain' => "app.example\r\nX: y"],
                ['domain' => 1],
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

    public function testAHostHeaderWithTheDefaultPortIsTheHost(): void
    {
        $https = new RecordingHttpRequest([
            'HTTP_HOST' => 'app.example:443',
            'HTTPS' => 'on',
            'HTTP_REFERER' => 'https://app.example/dashboard',
        ]);
        $http = new RecordingHttpRequest(
            ['HTTP_HOST' => 'App.Example:80', 'HTTP_REFERER' => 'http://app.example/dashboard'],
        );
        $otherPort = new RecordingHttpRequest(
            ['HTTP_HOST' => 'app.example:8443', 'HTTP_REFERER' => 'https://app.example/dashboard'],
        );

        self::assertSame('dashboard', $https->getRefererLocalized());
        self::assertSame('dashboard', $http->getRefererLocalized());
        self::assertNull($otherPort->getRefererLocalized());
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

    #[BackupGlobals(true)]
    public function testTheInputIsReadFromPhpsArrays(): void
    {
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable -- PHP's arrays are the input here
        $_GET = ['page' => 2, 'ids' => ['1', '2']];
        $_POST = ['name' => 'Ada'];
        $_COOKIE = ['theme' => 'dark'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';
        // phpcs:enable

        $request = new HttpRequest();

        self::assertTrue($request->hasGetParam('page'));
        self::assertSame('2', $request->getGetParam('page'));
        self::assertSame(['1', '2'], $request->getGetParam('ids'));
        self::assertSame('Ada', $request->getPostParam('name'));
        self::assertSame('dark', $request->getCookieParam('theme'));
        self::assertSame('POST', $request->getServerParam('REQUEST_METHOD'));
        self::assertTrue($request->isPostRequest());
    }

    public function testAbsentInputIsNull(): void
    {
        $request = new RecordingHttpRequest(
            ['HTTP_HOST' => 'app.example'],
            ['theme' => 'dark'],
            ['page' => '2'],
            ['name' => 'Ada'],
        );

        foreach (['absent', 'theme', 'page', 'name'] as $key) {
            self::assertSame($key === 'theme', $request->hasCookieParam($key), $key);
            self::assertSame($key === 'page', $request->hasGetParam($key), $key);
            self::assertSame($key === 'name', $request->hasPostParam($key), $key);
        }

        self::assertNull($request->getGetParam('absent'));
        self::assertNull($request->getPostParam('absent'));
        self::assertNull($request->getCookieParam('absent'));
        self::assertNull($request->getServerParam('absent'));
        self::assertFalse($request->hasServerParam('absent'));
        self::assertTrue($request->hasServerParam('HTTP_HOST'));
    }

    public function testOnlyAPostIsAPostRequest(): void
    {
        self::assertTrue(new RecordingHttpRequest(['REQUEST_METHOD' => 'POST'])->isPostRequest());
        self::assertFalse(new RecordingHttpRequest(['REQUEST_METHOD' => 'GET'])->isPostRequest());
        self::assertFalse(new RecordingHttpRequest(['REQUEST_METHOD' => 'post'])->isPostRequest());
        self::assertFalse(new RecordingHttpRequest()->isPostRequest());
    }

    public function testEveryResponseStartsWithTheHeadersAgainstCaching(): void
    {
        self::assertSame(
            [
                'Content-Type: text/html; charset=UTF-8',
                'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma: no-cache',
                'Expires: Thu, 01 Jan 1970 00:00:00 GMT',
            ],
            new RecordingHttpRequest()->getHeaders(),
        );
    }

    public function testAHeaderIsAddedAsItsLine(): void
    {
        $request = new RecordingHttpRequest();

        self::assertSame($request, $request->addHeader('X-Frame-Options', 'DENY'));
        self::assertSame('X-Frame-Options: DENY', $request->getHeaders()[4]);
    }

    public function testAHeaderNeedsANameAndAValue(): void
    {
        foreach ([['', 'value'], [' ', 'value'], ['X-Test', ''], ['X-Test', ' ']] as [$name, $value]) {
            try {
                new RecordingHttpRequest()->addHeader($name, $value);
                self::fail('accepted ' . json_encode([$name, $value], JSON_THROW_ON_ERROR));
            } catch (RuntimeException $e) {
                self::assertSame('A header needs a name and a value.', $e->getMessage());
            }
        }
    }

    public function testTheHeaderRefusalsSayWhy(): void
    {
        try {
            new RecordingHttpRequest()->addHeader('X Test', 'value');
            self::fail('accepted a name with a space');
        } catch (RuntimeException $e) {
            self::assertSame('A header name must be a token.', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A header value must not contain a control character.');

        new RecordingHttpRequest()->addHeader('X-Test', "a\nb");
    }

    public function testFlushSendsTheStatusTheHeadersAndTheBody(): void
    {
        $request = new RecordingHttpRequest();
        $request->setStatusCode(404)->addHeader('X-Test', '1')->setResponse('<p>not found</p>');

        $this->expectOutputString('<p>not found</p>');
        self::assertSame($request, $request->flush());

        self::assertSame(
            [
                'remove X-Powered-By',
                'status 404',
                'header Content-Type: text/html; charset=UTF-8 0',
                'header Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0 0',
                'header Pragma: no-cache 0',
                'header Expires: Thu, 01 Jan 1970 00:00:00 GMT 0',
                'header X-Test: 1 0',
            ],
            $request->getSent(),
        );
        self::assertSame([], $request->getHeaders(), 'sent once');
        self::assertSame('', $request->getResponse(), 'printed once');

        $request->flush();
        self::assertSame(['remove X-Powered-By', 'status 404'], array_slice($request->getSent(), 7));
    }

    public function testFlushSendsARedirectAfterTheHeaders(): void
    {
        $request = new RecordingHttpRequest();
        $request->setRawRedirect('/login');

        $this->expectOutputString('');
        $request->flush();

        self::assertSame('header Location: /login 303', $request->getSent()[6]);
        self::assertCount(7, $request->getSent());
        self::assertFalse($request->isRedirect(), 'sent once');
    }

    public function testFlushSendsNothingWhenAHeaderHasAControlCharacter(): void
    {
        $request = new RecordingHttpRequest();
        $request->addRawHeader("X-Test: a\r\nSet-Cookie: x=y");

        try {
            $request->flush();
            self::fail('sent a header with a line break');
        } catch (RuntimeException $e) {
            self::assertSame('A header must not contain a control character.', $e->getMessage());
        }

        self::assertSame(['remove X-Powered-By'], $request->getSent());
    }

    public function testFlushSendsNothingWhenTheRedirectTargetHasAControlCharacter(): void
    {
        $request = new RecordingHttpRequest();
        $request->setRawRedirect("/show/1\r\nX: y");

        try {
            $request->flush();
            self::fail('sent a redirect with a line break');
        } catch (RuntimeException $e) {
            self::assertSame('A redirect target must not contain a control character.', $e->getMessage());
        }

        self::assertSame(['remove X-Powered-By'], $request->getSent());
    }

    /**
     * @param list<array{string, float}> $expected
     */
    #[DataProvider('provideAcceptLanguages')]
    public function testTheAcceptedLanguagesAreReadWithTheirWeights(string $header, array $expected): void
    {
        $languages = array_map(
            static fn (stdClass $language): array => [$language->language, $language->quality],
            new RecordingHttpRequest(['HTTP_ACCEPT_LANGUAGE' => $header])->getAcceptedLanguages(),
        );

        self::assertSame($expected, $languages);
    }

    public function testWithoutAnAcceptLanguageHeaderNoLanguageIsAccepted(): void
    {
        self::assertSame([], new RecordingHttpRequest()->getAcceptedLanguages());
    }

    #[DataProvider('provideRoutes')]
    public function testTheRouteIsThePathWithoutTheBasePathAndTheQuery(string $uri, string $script, string $route): void
    {
        $request = $this->routed(['REQUEST_URI' => $uri, 'SCRIPT_NAME' => $script]);

        self::assertSame('page', $request->getRouteID());
        self::assertSame('PageController', $request->getController());
        self::assertSame(['path' => $route], $request->getRouteParams());
    }

    public function testARouteWithoutAMatchHasNoController(): void
    {
        $request = new RecordingHttpRequest(['REQUEST_URI' => '/missing']);
        $request->setRouteResolver($this->resolver(['home' => ['pattern' => '', 'controller' => 'HomeController']]));

        self::assertNull($request->getController());
        self::assertNull($request->getRouteID());
        self::assertNull($request->getRouteParams());
    }

    public function testWithoutARequestUriThereIsNoRoute(): void
    {
        $request = $this->routed([]);
        $request->removeServerParam('REQUEST_URI');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The request has no REQUEST_URI: its route is unknown.');

        $request->getController();
    }

    public function testALinkLiesUnderTheBasePath(): void
    {
        self::assertSame('/css/app.css', new RecordingHttpRequest()->getLink('css/app.css'));
        self::assertSame(
            '/app/css/app.css',
            new RecordingHttpRequest(['SCRIPT_NAME' => '/app/index.php'])->getLink('css/app.css'),
        );
        self::assertSame(
            '/web/app/',
            new RecordingHttpRequest(['SCRIPT_NAME' => '\web\app\index.php'])->getLink(''),
        );
        self::assertSame('', new RecordingHttpRequest(['SCRIPT_NAME' => '/index.php'])->basePath());
    }

    public function testAScriptPathOfOtherCharactersIsRefused(): void
    {
        foreach (['/my app/index.php', '/app/index file.php', "/app\n/index.php", '/app/(1)/index.php'] as $scriptName) {
            try {
                new RecordingHttpRequest(['SCRIPT_NAME' => $scriptName])->getLink('css/app.css');
                self::fail('accepted ' . json_encode($scriptName, JSON_THROW_ON_ERROR));
            } catch (RuntimeException $e) {
                self::assertSame(
                    'The script\'s path ' . $scriptName . ' has a segment other than letters, digits and _.%-.',
                    $e->getMessage(),
                );
            }
        }
    }

    public function testWithoutAScriptNameThereIsNoBasePath(): void
    {
        $request = new RecordingHttpRequest();
        $request->removeServerParam('SCRIPT_NAME');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The request has no SCRIPT_NAME: its base path is unknown.');

        $request->getLink('css/app.css');
    }

    public function testAnActionLinkNamesTheRouteItsQueryAndItsFragment(): void
    {
        $request = $this->routed(['SCRIPT_NAME' => '/app/index.php']);

        self::assertSame('/app/article/42', $request->getActionLink('article', ['id' => '42']));
        self::assertSame(
            '/app/article/42?page=2&sort%20by=new%20first#comment%204',
            $request->getActionLink(
                'article',
                ['id' => '42', 'page' => '2', 'sort by' => 'new first'],
                false,
                'comment 4',
            ),
        );
        self::assertSame(
            '/app/article/42',
            $request->getActionLink('article', ['id' => 42], null, ' '),
            'a blank fragment is none',
        );
    }

    public function testAnActionLinkWithATokenCarriesANewOne(): void
    {
        $request = $this->routed([]);

        $link = $request->getActionLink('article', ['id' => '42'], true);

        self::assertMatchesRegularExpression('~^/article/42\?stkn=[0-9a-f]{32}$~D', $link);
        self::assertSame($link, $request->getActionLink('article', ['id' => '42'], true), 'one token a request');
    }

    public function testAnActionLinkToAnUnknownRouteIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('There is no route missing.');

        $this->routed([])->getActionLink('missing');
    }

    public function testAnActionLinkWithoutItsParameterIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing parameter id');

        $this->routed([])->getActionLink('article');
    }

    public function testARedirectLeadsToARouteWithAPermanentStatusByDefault(): void
    {
        $request = $this->routed([]);

        self::assertFalse($request->isRedirect());
        self::assertSame($request, $request->setRedirect('article', ['id' => 42]));
        self::assertTrue($request->isRedirect());
        self::assertSame(['code' => 301, 'target' => '/article/42'], $request->getRedirect());

        $request->setRedirect('article', ['id' => 42], 303, false, 'top');
        self::assertSame(['code' => 303, 'target' => '/article/42#top'], $request->getRedirect());

        $request->setRedirect('article', ['id' => 42], 302, true);
        $redirect = $request->getRedirect();
        self::assertNotNull($redirect);
        self::assertMatchesRegularExpression('~^/article/42\?stkn=[0-9a-f]{32}$~D', $redirect['target']);
    }

    public function testARedirectNeedsARedirectStatus(): void
    {
        foreach ([300, 399] as $code) {
            $request = $this->routed([]);
            $request->setRedirect('article', ['id' => 1], $code);
            self::assertSame(['code' => $code, 'target' => '/article/1'], $request->getRedirect());
        }

        foreach ([200, 299, 400] as $code) {
            try {
                $this->routed([])->setRedirect('article', ['id' => 1], $code);
                self::fail('accepted ' . $code);
            } catch (InvalidArgumentException $e) {
                self::assertSame(
                    'A redirect\'s status is a code from 300 to 399, not ' . $code . '.',
                    $e->getMessage(),
                );
            }
        }
    }

    public function testARedirectTargetWithAControlCharacterIsRefused(): void
    {
        $request = new RecordingHttpRequest();
        $request->setRouteResolver($this->resolver(['broken' => ['pattern' => "line\nbreak", 'controller' => 'C']]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A redirect target must not contain a control character.');

        $request->setRedirect('broken');
    }

    public function testARedirectAndABodyExcludeEachOther(): void
    {
        $request = $this->routed([]);
        $request->setResponse('<p>body</p>');

        try {
            $request->setRedirect('article', ['id' => 1]);
            self::fail('a redirect after a body');
        } catch (RuntimeException $e) {
            self::assertSame('A redirect cannot follow a response body.', $e->getMessage());
        }

        $request = $this->routed([]);
        $request->setRedirect('article', ['id' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A response body cannot follow a redirect.');

        $request->setResponse('<p>body</p>');
    }

    public function testTheResponseIsTheBodySoFar(): void
    {
        $request = new RecordingHttpRequest();

        self::assertSame('', $request->getResponse());
        self::assertSame($request, $request->setResponse('<p>one</p>'));
        self::assertSame('<p>one</p>', $request->getResponse());
    }

    public function testTheStatusIsAnHttpStatus(): void
    {
        $request = new RecordingHttpRequest();

        self::assertSame($request, $request->setStatusCode(100)->setStatusCode(599));

        foreach ([99, 600] as $statusCode) {
            try {
                $request->setStatusCode($statusCode);
                self::fail('accepted ' . $statusCode);
            } catch (RuntimeException $e) {
                self::assertSame(
                    'An HTTP status is a code from 100 to 599, not ' . $statusCode . '.',
                    $e->getMessage(),
                );
            }
        }
    }

    public function testATokenIsCheckedFromTheQueryString(): void
    {
        $session = new ArraySessionService();
        $token = $this->tokens($session)->getNewToken();

        self::assertFalse($this->tokened($session, [])->hasCorrectToken(), 'no token');
        self::assertFalse($this->tokened($session, ['stkn' => [$token]])->hasCorrectToken(), 'a list');
        self::assertTrue($this->tokened($session, ['stkn' => $token])->hasCorrectToken());
        self::assertFalse($this->tokened($session, ['stkn' => $token])->hasCorrectToken(), 'spent');
    }

    public function testTheRefererUnderTheBasePathIsItsRoute(): void
    {
        $request = new RecordingHttpRequest([
            'HTTP_HOST' => 'app.example',
            'HTTP_REFERER' => 'https://app.example/app/articles?page=3',
            'SCRIPT_NAME' => '/app/index.php',
        ]);

        self::assertSame('articles?page=3', $request->getRefererLocalized());

        $request = new RecordingHttpRequest([
            'HTTP_HOST' => 'app.example',
            'HTTP_REFERER' => 'https://app.example/application/x',
            'SCRIPT_NAME' => '/app/index.php',
        ]);

        self::assertSame(
            'application/x',
            $request->getRefererLocalized(),
            'a segment that only starts like the base path',
        );
        self::assertSame(
            null,
            new RecordingHttpRequest(
                ['HTTP_HOST' => 'app.example', 'HTTP_REFERER' => 'https://app.example/app/', 'SCRIPT_NAME' => '/app/index.php'],
            )
                ->getRefererLocalized(),
            'the base path itself',
        );
    }

    public function testABlankRefererIsNone(): void
    {
        foreach (['', '  '] as $referer) {
            self::assertNull(new RecordingHttpRequest(['HTTP_REFERER' => $referer])->getRefererRaw());
        }

        self::assertSame(
            'https://app.example/x',
            new RecordingHttpRequest(['HTTP_REFERER' => 'https://app.example/x'])->getRefererRaw(),
        );
    }

    public function testACookiesBlockOfAnotherTypeIsRefused(): void
    {
        $request = new RecordingHttpRequest();
        $request->setBeanFactory(new BeanFactory(['cookies' => 'secure']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The configuration\'s cookies must be an array, not string.');

        $request->setCookieParam('a', 'b');
    }

    /**
     * A request with the routes of these tests.
     *
     * @param array<string, string> $server
     */
    private function routed(array $server): RecordingHttpRequest
    {
        $request = new RecordingHttpRequest($server);
        $request->setRouteResolver($this->resolver([
            'article' => ['pattern' => 'article/(?P<id>[0-9]+)', 'controller' => 'ArticleController'],
            'page' => ['pattern' => '(?P<path>.*)', 'controller' => 'PageController'],
        ]));
        $request->setXsrfTokenService($this->tokens(new ArraySessionService()));

        return $request;
    }

    /**
     * @param array<string, array{pattern: string, controller: string}> $routes
     */
    private function resolver(array $routes): RouteResolver
    {
        $resolver = new RouteResolver();
        $resolver->setConfig(['routes' => $routes]);

        return $resolver;
    }

    private function tokens(ArraySessionService $session): XsrfTokenService
    {
        $tokens = new XsrfTokenService();
        $tokens->setSessionService($session);

        return $tokens;
    }

    /**
     * A request of the session with this query.
     *
     * @param array<string, string|array<mixed>> $get
     */
    private function tokened(ArraySessionService $session, array $get): RecordingHttpRequest
    {
        $request = new RecordingHttpRequest(get: $get);
        $request->setXsrfTokenService($this->tokens($session));

        return $request;
    }
}
