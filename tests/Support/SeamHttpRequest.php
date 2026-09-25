<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

/**
 * The recording request whose protected methods note their calls and then do their work, as an application's request
 * that changes one of them would (its cookie defaults, say).
 */
final class SeamHttpRequest extends RecordingHttpRequest
{
    /**
     * @var list<string>
     */
    private array $calls = [];

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

    /**
     * @param array<string, mixed> $options
     *
     * @return array{path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'}
     */

    protected function getCookieOptions(array $options): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::getCookieOptions($options);
    }

    /**
     * @return array<string, mixed>
     */

    protected function getCookieDefaults(): array
    {
        $this->calls[] = __FUNCTION__;

        return parent::getCookieDefaults();
    }

    protected function getRefererPathOnThisHost(string $referer): ?string
    {
        $this->calls[] = __FUNCTION__;

        return parent::getRefererPathOnThisHost($referer);
    }

    protected function getRoute(): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::getRoute();
    }

    protected function isHttpsRequest(): bool
    {
        $this->calls[] = __FUNCTION__;

        return parent::isHttpsRequest();
    }

    protected function getDirname(string $path): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::getDirname($path);
    }
}
