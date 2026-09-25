<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Request\HttpRequest;
use ampf\Tests\Support\PassThroughHttpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The response through PHP's own functions — header(), http_response_code(), setcookie() —, each test in a process of
 * its own: the command line sends no header, but it takes them all as a web server's PHP does, and it keeps the status.
 */
#[CoversClass(HttpRequest::class)]
#[RunTestsInSeparateProcesses]
final class HttpRequestFlushTest extends TestCase
{
    public function testTheResponseGoesOutThroughPhp(): void
    {
        $request = new PassThroughHttpRequest();
        $request->setStatusCode(201);
        $request->setCookieParam('theme', 'dark');
        $request->setResponse('<p>created</p>');

        $this->expectOutputString('<p>created</p>');
        $request->flush();

        self::assertSame(201, http_response_code());
        self::assertSame(
            [
                'cookie theme',
                'remove X-Powered-By',
                'status 201',
                'header Content-Type: text/html; charset=UTF-8',
                'header Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'header Pragma: no-cache',
                'header Expires: Thu, 01 Jan 1970 00:00:00 GMT',
            ],
            $request->getCalls(),
        );
    }

    public function testARedirectGoesOutWithItsStatus(): void
    {
        $request = new PassThroughHttpRequest();
        $request->setRawRedirect('/elsewhere', 303);

        $request->flush();

        self::assertSame(303, http_response_code(), 'the Location header carries the status');
        self::assertContains('header Location: /elsewhere', $request->getCalls());
    }
}
