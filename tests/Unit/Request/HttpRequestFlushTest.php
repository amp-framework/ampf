<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Request\HttpRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The response through PHP's own functions — header(), http_response_code(), setcookie() —, each test in a process of
 * its own: the command line sends no header, but it takes them all as a web server's PHP does.
 */
#[CoversClass(HttpRequest::class)]
#[RunTestsInSeparateProcesses]
final class HttpRequestFlushTest extends TestCase
{
    public function testTheResponseGoesOutThroughPhp(): void
    {
        $request = new HttpRequest();
        $request->setStatusCode(201);
        $request->addHeader('X-Test', 'yes');
        $request->setCookieParam('theme', 'dark');
        $request->setResponse('<p>created</p>');

        $this->expectOutputString('<p>created</p>');
        $request->flush();

        self::assertSame(201, http_response_code());
    }
}
