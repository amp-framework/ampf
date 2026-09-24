<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Request\HttpRequest;

/**
 * The real request with its input given by the test instead of PHP's superglobals — the server's variables, the
 * cookies, the query, the form and the raw body — and the cookies recorded instead of handed to PHP (a test process
 * has long sent its output).
 */
final class RecordingHttpRequest extends HttpRequest
{
    /**
     * @var list<array{name: string, value: string, options: array<string, mixed>}>
     */
    private array $sentCookies = [];

    /**
     * @param array<string, string> $server
     * @param array<string, string> $cookie
     * @param array<string, string|array<mixed>> $get
     * @param array<string, string|array<mixed>> $post
     * @param ?string $body the raw body, in place of php://input
     */
    public function __construct(
        array $server = [],
        array $cookie = [],
        array $get = [],
        array $post = [],
        ?string $body = null,
    ) {
        parent::__construct();

        $this->server = $server + ['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/'];
        $this->cookie = $cookie;
        $this->get = $get;
        $this->post = $post;
        $this->body = $body;
    }

    /**
     * The cookies handed out so far, in their order.
     *
     * @return list<array{name: string, value: string, options: array<string, mixed>}>
     */
    public function getSentCookies(): array
    {
        return $this->sentCookies;
    }

    /**
     * @return array<int, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * The redirect set, null when there is none.
     *
     * @return ?array{code: int, target: string}
     */
    public function getRedirect(): ?array
    {
        return $this->responseRedirect;
    }

    /**
     * Sets a redirect target the way a subclass may, past setRedirect()'s checks.
     */
    public function setRawRedirect(string $target): void
    {
        $this->responseRedirect = ['code' => 303, 'target' => $target];
    }

    /**
     * @param array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'} $options
     */
    protected function sendCookie(string $key, string $value, array $options): void
    {
        $this->sentCookies[] = ['name' => $key, 'value' => $value, 'options' => $options];
    }
}
