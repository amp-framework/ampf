<?php

declare(strict_types=1);

namespace ampf\Testing;

use ampf\Request\HttpRequest;
use ampf\Request\UploadedFile;
use PHPUnit\Framework\Assert;

/**
 * The real request with its input given by the test instead of PHP's superglobals — the route, the query, the form,
 * the method, the cookies, the web server's variables, the raw body and the uploaded files (files the test wrote,
 * TestUploadedFile). Routing, tokens, links, redirects and the response run the framework's code; flush() sends
 * nothing, and the cookies the application sets are recorded instead of handed to PHP, so that a test reads the whole
 * response: its status, its headers, its cookies, its redirect and its page.
 */
class TestHttpRequest extends HttpRequest
{
    /**
     * The cookies the application set or deleted, by name: the last value and expiry of each.
     *
     * @var array<string, array{value: string, expires: int}>
     */
    protected array $cookiesSet = [];

    /**
     * The attributes each cookie was last set or deleted with, by name.
     *
     * @var array<string, array{path: string, domain: string, secure: bool, httponly: bool, samesite: string}>
     */
    protected array $cookieAttributes = [];

    /**
     * @param string $path the route as a browser asks for it, without its query (`notes`, `/hello/ada`)
     * @param array<string, string|array<mixed>> $get the query
     * @param array<string, string|array<mixed>> $post the form
     * @param array<string, string> $cookie
     * @param array<string, string> $server what the web server passes on (`HTTP_REFERER`, `HTTPS`), over the defaults:
     *     the method, the request line of the path and the query, the script `/index.php` (the site's root is the base
     *     path), the host `example.test` and the client `192.0.2.1`
     * @param string $body the raw body (an API client's JSON)
     * @param array<string, string|array<mixed>> $files the uploaded files in `$_FILES`' shape: for a field, its names,
     *     sizes, temporary paths (files the test wrote) and errors
     */
    public function __construct(
        string $path = '',
        array $get = [],
        array $post = [],
        string $method = 'GET',
        array $cookie = [],
        array $server = [],
        string $body = '',
        array $files = [],
    ) {
        parent::__construct();

        $query = http_build_query($get, '', '&', PHP_QUERY_RFC3986);

        $this->get = $get;
        $this->post = $post;
        $this->cookie = $cookie;
        $this->body = $body;
        $this->files = $files;
        $this->server = $server + [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => '/' . ltrim($path, '/') . ($query === '' ? '' : '?' . $query),
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST' => 'example.test',
            'SERVER_NAME' => 'example.test',
            'REMOTE_ADDR' => '192.0.2.1',
        ];
    }

    /** The client's address as the web server reports it (REMOTE_ADDR). */
    public function setRemoteAddress(string $address): self
    {
        return $this->setServerParam('REMOTE_ADDR', $address);
    }

    /** A variable as the web server passes it on: a header such as `HTTP_SEC_FETCH_SITE` for `Sec-Fetch-Site`. */
    public function setServerParam(string $name, string $value): self
    {
        $this->server[$name] = $value;

        return $this;
    }

    /**
     * Sends nothing: the response stays for the test to read.
     *
     * @phpstan-pure
     */
    public function flush(): self
    {
        return $this;
    }

    public function getResponseStatusCode(): int
    {
        return $this->responseStatusCode;
    }

    /**
     * The response's headers as `Name: value`, in the order they were added (a removed one is gone).
     *
     * @return list<string>
     */
    public function getHeaders(): array
    {
        return array_values($this->headers);
    }

    /** The value of the last header of the name, whatever its case; null when there is none. */
    public function getHeader(string $name): ?string
    {
        $value = null;

        foreach ($this->headers as $header) {
            if (str_starts_with(strtolower($header), strtolower($name) . ': ')) {
                $value = substr($header, strlen($name) + 2);
            }
        }

        return $value;
    }

    /**
     * The cookies the application set or deleted, by name (a deletion is an empty value that expires at 0).
     *
     * @return array<string, array{value: string, expires: int}>
     */
    public function getCookiesSet(): array
    {
        return $this->cookiesSet;
    }

    /**
     * The attributes the cookie was last set or deleted with; null when the application did neither.
     *
     * @return ?array{path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    public function getCookieAttributes(string $key): ?array
    {
        return $this->cookieAttributes[$key] ?? null;
    }

    /** The redirect's target, null when the response is none. */
    public function getRedirect(): ?string
    {
        return $this->responseRedirect['target'] ?? null;
    }

    /** The response is a redirect to the target with the status (303, the answer to a form, unless told). */
    public function assertRedirect(string $target, int $code = 303): void
    {
        $redirect = $this->responseRedirect;

        Assert::assertNotNull($redirect, 'The response is no redirect.');
        Assert::assertSame($target, $redirect['target'], 'The redirect goes elsewhere.');
        Assert::assertSame($code, $redirect['code'], 'The redirect has another status.');
    }

    /** The response's page, which a redirect is not. */
    public function requireResponse(): string
    {
        Assert::assertFalse(
            $this->isRedirect(),
            'The response is a redirect to ' . $this->getRedirect() . ', not a page.',
        );

        return $this->getResponse();
    }

    /**
     * Records the cookie instead of handing it to PHP, which a test process has long sent its headers in.
     *
     * @param array{
     *     expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'
     * } $options
     */
    protected function sendCookie(string $key, string $value, array $options): void
    {
        ['expires' => $expires] = $options;
        unset($options['expires']);

        $this->cookiesSet[$key] = ['value' => $value, 'expires' => $expires];
        $this->cookieAttributes[$key] = $options;
    }

    /** An upload of the test: its temporary path is a file the test wrote (TestUploadedFile). */
    protected function createUploadedFile(
        string $clientName,
        int $size,
        string $temporaryPath,
        int $error,
    ): UploadedFile {
        return new TestUploadedFile($clientName, $size, $temporaryPath, $error);
    }
}
