<?php

declare(strict_types=1);

namespace ampfTest\Support;

use ampf\services\session\SessionService;

/**
 * A session in an array, for the tests of the services that keep something in the session: no session_start(), no
 * cookie. close() and destroy() behave as PHP's session does for the code above it: nothing written afterwards is
 * kept.
 */
final class ArraySessionService implements SessionService
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    private bool $open = true;

    public function close(): void
    {
        $this->open = false;
    }

    public function destroy(): void
    {
        $this->values = [];
        $this->open = false;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function hasAttribute(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function removeAttribute(string $key): void
    {
        if ($this->open) {
            unset($this->values[$key]);
        }
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if ($this->open) {
            $this->values[$key] = $value;
        }
    }
}
