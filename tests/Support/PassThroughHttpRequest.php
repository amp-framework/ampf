<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Request\HttpRequest;

/**
 * The real request whose response leaves through PHP's own functions, each seam noted on its way: for a process of its
 * own, where PHP takes the headers as a web server's PHP does.
 */
final class PassThroughHttpRequest extends HttpRequest
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * The seams called so far, in their order.
     *
     * @return list<string>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /** A redirect as flush() sends it, without a route to build it from. */
    public function setRawRedirect(string $target, int $code): void
    {
        $this->responseRedirect = ['code' => $code, 'target' => $target];
    }

    /**
     * @param array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'} $options
     */

    protected function sendCookie(string $key, string $value, array $options): void
    {
        $this->calls[] = 'cookie ' . $key;

        parent::sendCookie($key, $value, $options);
    }

    protected function sendHeader(string $header, int $statusCode): void
    {
        $this->calls[] = 'header ' . $header;

        parent::sendHeader($header, $statusCode);
    }

    protected function sendStatusCode(int $statusCode): void
    {
        $this->calls[] = 'status ' . $statusCode;

        parent::sendStatusCode($statusCode);
    }

    protected function removeHeader(string $name): void
    {
        $this->calls[] = 'remove ' . $name;

        parent::removeHeader($name);
    }
}
