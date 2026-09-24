<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Request\HttpRequest;

/**
 * The real request with its input given by the test instead of PHP's superglobals — the server's variables, the
 * cookies, the query, the form and the raw body — and what leaves it (cookies, the status, the headers) recorded
 * instead of handed to PHP: a test process has long sent its output.
 */
final class RecordingHttpRequest extends HttpRequest
{
    /**
     * @var list<array{name: string, value: string, options: array<string, mixed>}>
     */
    private array $sentCookies = [];

    /**
     * What flush() handed to PHP, in its order: `status <code>`, `header <line> <code>`, `remove <name>`.
     *
     * @var list<string>
     */
    private array $sent = [];

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
     * @phpstan-impure
     *
     * @return ?array{code: int, target: string}
     */
    public function getRedirect(): ?array
    {
        return $this->responseRedirect;
    }

    /**
     * What flush() handed to PHP so far, in its order.
     *
     * @return list<string>
     */
    public function getSent(): array
    {
        return $this->sent;
    }

    /**
     * Sets a redirect target the way a subclass may, past setRedirect()'s checks.
     */
    public function setRawRedirect(string $target): void
    {
        $this->responseRedirect = ['code' => 303, 'target' => $target];
    }

    /**
     * Adds a header line the way a subclass may, past addHeader()'s checks.
     */
    public function addRawHeader(string $header): void
    {
        $this->headers[] = $header;
    }

    /**
     * Takes a server variable away, as a web server that does not pass it.
     */
    public function removeServerParam(string $key): void
    {
        unset($this->server[$key]);
    }

    /**
     * The base path, as the request's links and routes use it.
     */
    public function basePath(): string
    {
        return $this->getBasePath();
    }

    /**
     * @param array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'} $options
     */
    protected function sendCookie(string $key, string $value, array $options): void
    {
        $this->sentCookies[] = ['name' => $key, 'value' => $value, 'options' => $options];
    }

    protected function sendHeader(string $header, int $statusCode = 0): void
    {
        $this->sent[] = 'header ' . $header . ' ' . $statusCode;
    }

    protected function sendStatusCode(int $statusCode): void
    {
        $this->sent[] = 'status ' . $statusCode;
    }

    protected function removeHeader(string $name): void
    {
        $this->sent[] = 'remove ' . $name;
    }
}
