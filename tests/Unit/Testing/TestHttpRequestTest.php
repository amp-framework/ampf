<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing;

use ampf\Bean\BeanFactory;
use ampf\Router\RouteResolver;
use ampf\Testing\TestHttpRequest;
use ampf\Testing\TestUploadedFile;
use ampf\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
#[CoversClass(TestHttpRequest::class)]
final class TestHttpRequestTest extends TestCase
{
    public function testItIsTheRequestTheTestDescribes(): void
    {
        $request = $this->routed(new TestHttpRequest(
            'hello/ada',
            ['tab' => 'notes', 'ids' => ['1', '2']],
            ['text' => 'Buy milk'],
            'POST',
            ['theme' => 'dark'],
            ['HTTP_REFERER' => 'http://example.test/notes'],
            '{"text":"Buy milk"}',
        ));

        self::assertSame('hello', $request->getRouteID());
        self::assertSame(['name' => 'ada'], $request->getRouteParams());
        self::assertSame('notes', $request->getGetString('tab'));
        self::assertSame(['1', '2'], $request->getParamStrings('ids'));
        self::assertSame('Buy milk', $request->getPostString('text'));
        self::assertTrue($request->isPostRequest());
        self::assertSame('dark', $request->getCookieParam('theme'));
        self::assertSame('notes', $request->getRefererLocalized());
        self::assertSame('{"text":"Buy milk"}', $request->getBody());
    }

    public function testItIsAGetToTheHomePageOfExampleTestUnlessTold(): void
    {
        $request = $this->routed(new TestHttpRequest());

        self::assertSame('home', $request->getRouteID());
        self::assertFalse($request->isPostRequest());
        self::assertSame('GET', $request->getServerParam('REQUEST_METHOD'));
        self::assertSame('/', $request->getServerParam('REQUEST_URI'));
        self::assertSame('/index.php', $request->getServerParam('SCRIPT_NAME'));
        self::assertSame('example.test', $request->getServerParam('HTTP_HOST'));
        self::assertSame('example.test', $request->getServerParam('SERVER_NAME'));
        self::assertSame('192.0.2.1', $request->getServerParam('REMOTE_ADDR'), 'an address for documentation');
        self::assertSame('', $request->getBody(), 'no body, and no read of PHP\'s input');
        self::assertSame('/notes', $request->getActionLink('notes'), 'the site\'s root is the base path');
    }

    public function testTheRequestLineCarriesTheQuery(): void
    {
        $request = new TestHttpRequest('/notes', ['tab' => 'all', 'q' => 'a b', 'ids' => ['1']]);

        self::assertSame('/notes?tab=all&q=a%20b&ids%5B0%5D=1', $request->getServerParam('REQUEST_URI'));
        self::assertSame('/notes', new TestHttpRequest('notes')->getServerParam('REQUEST_URI'));
    }

    public function testWhatTheWebServerPassesOnWinsOverTheDefaults(): void
    {
        $request = new TestHttpRequest('notes', server: [
            'HTTP_HOST' => 'app.example',
            'REQUEST_METHOD' => 'PUT',
            'HTTPS' => 'on',
        ]);

        self::assertSame('app.example', $request->getServerParam('HTTP_HOST'));
        self::assertSame('PUT', $request->getServerParam('REQUEST_METHOD'));
        self::assertSame('on', $request->getServerParam('HTTPS'));
        self::assertSame('example.test', $request->getServerParam('SERVER_NAME'));
    }

    public function testNothingOfTheProcessLeaksIntoIt(): void
    {
        $_GET['leak'] = 'get';
        $_POST['leak'] = 'post';
        $_COOKIE['leak'] = 'cookie';
        $_SERVER['HTTP_LEAK'] = 'server';
        $_FILES['leak'] = ['name' => 'a.txt', 'type' => '', 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK, 'size' => 1];

        try {
            $request = new TestHttpRequest();
        } finally {
            unset($_GET['leak'], $_POST['leak'], $_COOKIE['leak'], $_SERVER['HTTP_LEAK'], $_FILES['leak']);
        }

        self::assertFalse($request->hasGetParam('leak'));
        self::assertFalse($request->hasPostParam('leak'));
        self::assertFalse($request->hasCookieParam('leak'));
        self::assertFalse($request->hasServerParam('HTTP_LEAK'));
        self::assertSame([], $request->getUploadedFiles('leak'));
    }

    public function testTheFormsFilesAreTheOnesTheTestWrote(): void
    {
        $directory = new TemporaryDirectory('ampf-test-request');

        try {
            $path = $directory->getPath() . '/php1';
            file_put_contents($path, 'Hello');
            $request = new TestHttpRequest('upload', method: 'POST', files: ['files' => [
                'name' => ['a.txt', ''],
                'type' => ['text/plain', ''],
                'tmp_name' => [$path, ''],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
                'size' => [5, 0],
            ]]);

            $files = $request->getUploadedFiles('files');

            self::assertCount(1, $files, 'an input left empty is no file');
            self::assertInstanceOf(TestUploadedFile::class, $files[0]);
            self::assertSame(
                ['a.txt', 5, $path, UPLOAD_ERR_OK],
                [$files[0]->clientName, $files[0]->size, $files[0]->temporaryPath, $files[0]->error],
            );
            self::assertTrue($files[0]->isUploadedFile());
            self::assertTrue($files[0]->moveTo($directory->getPath() . '/moved'));
            self::assertStringEqualsFile($directory->getPath() . '/moved', 'Hello');
            self::assertSame([], new TestHttpRequest()->getUploadedFiles('files'), 'none unless the test gives them');
        } finally {
            $directory->remove();
        }
    }

    public function testTheClientsAddressAndAHeaderAreSetOnTheWay(): void
    {
        $request = new TestHttpRequest();

        self::assertSame($request, $request->setRemoteAddress('198.51.100.7'));
        self::assertSame($request, $request->setServerParam('HTTP_SEC_FETCH_SITE', 'cross-site'));
        self::assertSame('198.51.100.7', $request->getServerParam('REMOTE_ADDR'));
        self::assertFalse($request->comesFromThisSite());
    }

    public function testFlushSendsNothingAndKeepsTheResponse(): void
    {
        $request = new TestHttpRequest();
        $request->setStatusCode(201)->addHeader('X-Id', '7')->setResponse('<p>Made</p>');

        $this->expectOutputString('');
        self::assertSame($request, $request->flush());
        self::assertSame(201, $request->getResponseStatusCode());
        self::assertSame('7', $request->getHeader('X-Id'));
        self::assertSame('<p>Made</p>', $request->requireResponse());
    }

    public function testTheHeadersAreThoseOfTheResponseInTheirOrder(): void
    {
        $request = new TestHttpRequest();

        self::assertSame(200, $request->getResponseStatusCode());
        self::assertSame('text/html; charset=UTF-8', $request->getHeader('Content-Type'));

        $request->removeHeader('Pragma')->removeHeader('Expires')->removeHeader('Cache-Control')
            ->addHeader('Link', '</a.css>; rel=preload')
            ->addHeader('link', '</b.css>; rel=preload')
        ;

        self::assertSame(
            ['Content-Type: text/html; charset=UTF-8', 'Link: </a.css>; rel=preload', 'link: </b.css>; rel=preload'],
            $request->getHeaders(),
        );
        self::assertSame('</b.css>; rel=preload', $request->getHeader('LINK'), 'the last one, whatever the case');
        self::assertNull($request->getHeader('Pragma'), 'removed');
        self::assertNull($request->getHeader('Content'), 'a name is the whole name');
    }

    public function testTheCookiesTheApplicationSetAreRecordedWithTheirAttributes(): void
    {
        $request = new TestHttpRequest(cookie: ['old' => '1'], server: ['HTTPS' => 'on']);
        $request->setBeanFactory(new BeanFactory(['cookies' => ['samesite' => 'Strict']]));

        self::assertSame([], $request->getCookiesSet());
        self::assertNull($request->getCookieAttributes('theme'));

        $request->setCookieParam('theme', 'light')
            ->setCookieParam('theme', 'dark', 1_900_000_000, ['httponly' => false])
            ->destroyCookieParam('old')
        ;

        self::assertSame(
            ['theme' => ['value' => 'dark', 'expires' => 1_900_000_000], 'old' => ['value' => '', 'expires' => 0]],
            $request->getCookiesSet(),
            'a deletion is an empty value that expires at 0',
        );
        self::assertSame(
            ['path' => '/', 'domain' => '', 'secure' => true, 'httponly' => false, 'samesite' => 'Strict'],
            $request->getCookieAttributes('theme'),
        );
        self::assertSame(
            ['path' => '/', 'domain' => '', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict'],
            $request->getCookieAttributes('old'),
        );
        self::assertSame('dark', $request->getCookieParam('theme'), 'the rest of the request sees it');
    }

    public function testARedirectIsRecordedAndAsserted(): void
    {
        $request = $this->routed(new TestHttpRequest());

        self::assertNull($request->getRedirect());

        $request->setRedirect('notes', ['tab' => 'all'], 303);

        self::assertSame('/notes?tab=all', $request->getRedirect());
        $request->assertRedirect('/notes?tab=all');
        $request->assertRedirect('/notes?tab=all', 303);

        $request->dropRedirect()->setRedirect('home', null, 302);

        $request->assertRedirect('/', 302);
    }

    public function testAResponseThatIsNoRedirectFailsTheAssertion(): void
    {
        $request = new TestHttpRequest();
        $request->setResponse('<p>A page</p>');

        $this->assertFailure(
            "The response is no redirect.\nFailed asserting that null is not null.",
            static fn () => $request->assertRedirect('/notes'),
        );
    }

    public function testARedirectElsewhereFailsTheAssertion(): void
    {
        $request = $this->routed(new TestHttpRequest());
        $request->setRedirect('notes', null, 302);

        $this->assertFailure(
            "The redirect goes elsewhere.\nFailed asserting that two strings are identical.",
            static fn () => $request->assertRedirect('/'),
        );
        $this->assertFailure(
            "The redirect has another status.\nFailed asserting that 302 is identical to 303.",
            static fn () => $request->assertRedirect('/notes'),
        );
    }

    public function testAResponseIsRequiredToBeAPage(): void
    {
        $request = $this->routed(new TestHttpRequest());

        self::assertSame('', $request->requireResponse(), 'an empty page is one');

        $request->setRedirect('notes');

        $this->assertFailure(
            "The response is a redirect to /notes, not a page.\nFailed asserting that true is false.",
            static fn () => $request->requireResponse(),
        );
    }

    /**
     * The assertion fails with exactly this message.
     *
     * @param callable(): mixed $assertion
     */
    private function assertFailure(string $message, callable $assertion): void
    {
        try {
            $assertion();
        } catch (AssertionFailedError $e) {
            self::assertSame($message, $e->getMessage());

            return;
        }

        self::fail('The assertion passed.');
    }

    private function routed(TestHttpRequest $request): TestHttpRequest
    {
        $resolver = new RouteResolver();
        $resolver->setConfig(['routes' => [
            'home' => ['pattern' => '', 'controller' => 'HomeController'],
            'hello' => ['pattern' => 'hello/(?P<name>[a-z]+)', 'controller' => 'HelloController'],
            'notes' => ['pattern' => 'notes', 'controller' => 'NotesController'],
        ]]);
        $request->setRouteResolver($resolver);

        return $request;
    }
}
