<?php

declare(strict_types=1);

namespace ampfTest\Support;

use ampf\requests\impl\DefaultHttp;

/**
 * The real request with the server's variables given by the test and the cookies recorded instead of handed to PHP
 * (a test process has long sent its output).
 */
final class RecordingHttp extends DefaultHttp
{
    /**
     * @var list<array{name: string, value: string, options: array<string, mixed>}>
     */
    private array $sentCookies = [];

    /**
     * @param array<string, string> $server
     * @param array<string, string> $cookie
     */
    public function __construct(array $server = [], array $cookie = [])
    {
        parent::__construct();

        $this->server = $server + ['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/'];
        $this->cookie = $cookie;
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
