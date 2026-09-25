<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Service\Session\SessionService;

/**
 * PHP's session behind the framework's service, its protected methods noting their calls before they do their work —
 * the starts of PHP's session counted, the cookies recorded instead of handed to PHP, and a start that may fail.
 */
final class SeamSessionService extends SessionService
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    /**
     * @var list<array{name: string, value: string, options: array<string, mixed>}>
     */
    private array $sentCookies = [];

    private bool $refusesToStart = false;

    public function refuseToStart(): void
    {
        $this->refusesToStart = true;
    }

    /**
     * The names of the protected methods called so far, each once, sorted.
     *
     * @return list<string>
     */
    public function getCalledMethods(): array
    {
        $methods = array_values(array_unique($this->calls));
        sort($methods);

        return $methods;
    }

    /** How often PHP's session was started. */
    public function getStarts(): int
    {
        return count(array_keys($this->calls, 'openSession', true));
    }

    /**
     * @return list<array{name: string, value: string, options: array<string, mixed>}>
     */
    public function getSentCookies(): array
    {
        return $this->sentCookies;
    }

    protected function start(): void
    {
        $this->calls[] = __FUNCTION__;

        parent::start();
    }

    /**
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'}
     */

    protected function getCookieParameters(): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::getCookieParameters();
    }

    /**
     * @return array<mixed>
     */

    protected function getSessionConfig(): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::getSessionConfig();
    }

    protected function isHttpsRequest(): bool
    {
        $this->calls[] = __FUNCTION__;

        return parent::isHttpsRequest();
    }

    /**
     * @param array<string, bool> $options
     */

    protected function openSession(array $options): bool
    {
        $this->calls[] = __FUNCTION__;

        return !$this->refusesToStart && parent::openSession($options);
    }

    /**
     * @param array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string} $options
     */

    protected function sendCookie(string $name, string $value, array $options): void
    {
        $this->calls[] = __FUNCTION__;
        $this->sentCookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
    }
}
