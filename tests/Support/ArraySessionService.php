<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Service\Session\SessionServiceInterface;

/**
 * A session in an array, for the tests of the services that keep something in the session: no session_start(), no
 * cookie. close() and destroy() behave as PHP's session does for the code above it: nothing written afterwards is
 * kept; regenerateId() and renew() give the session a new id, which id() shows.
 */
final class ArraySessionService implements SessionServiceInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    private bool $open = true;

    private int $id = 1;

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

    /** The session's id: 1 at first, one more after each regenerateId() and renew(). */
    public function id(): int
    {
        return $this->id;
    }

    public function regenerateId(): void
    {
        $this->id++;
    }

    public function renew(): void
    {
        $this->values = [];
        $this->id++;
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
