<?php

declare(strict_types=1);

namespace ampf\Testing;

use ampf\Service\Session\SessionServiceInterface;
use RuntimeException;

/**
 * The session of one browser in a test: no session_start(), no cookie, and a serialized copy of every value, as PHP's
 * session keeps one — a value that cannot be serialized fails here as it fails on a server, and an object changed after
 * setAttribute() is kept as it was set. It behaves as the framework's session where an application can tell: after
 * close() or destroy() what the request writes is not kept (nor read back) until the next request opens the session
 * again (open()); regenerateId() and renew() give the session a new id, which id() shows, and are refused once the
 * session is closed or destroyed; an attribute needs a name.
 */
class MemorySessionService implements SessionServiceInterface
{
    /**
     * The values, serialized.
     *
     * @var array<string, string>
     */
    protected array $values = [];

    protected bool $open = true;

    protected int $id = 1;

    /** A request opens the session: what it writes is kept again. */
    public function open(): void
    {
        $this->open = true;
    }

    /** The session's id: 1 at first, one more with every regenerateId() and renew(). */
    public function id(): int
    {
        return $this->id;
    }

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
        return isset($this->values[$key])
            ? unserialize($this->values[$key])
            : null;
    }

    /** Whether the attribute is set to something other than null, as isset() over PHP's session says. */
    public function hasAttribute(string $key): bool
    {
        return $this->getAttribute($key) !== null;
    }

    /**
     * @throws RuntimeException when the session is closed or destroyed
     */
    public function regenerateId(): void
    {
        if (!$this->open) {
            throw new RuntimeException('Failed to give the session a new id.');
        }

        $this->id++;
    }

    public function removeAttribute(string $key): void
    {
        if ($this->open) {
            unset($this->values[$key]);
        }
    }

    /**
     * @throws RuntimeException when the session is closed or destroyed
     */
    public function renew(): void
    {
        $this->regenerateId();

        $this->values = [];
    }

    /**
     * @throws RuntimeException for a blank name
     */
    public function setAttribute(string $key, mixed $value): void
    {
        if (trim($key) === '') {
            throw new RuntimeException('A session attribute needs a name.');
        }

        if ($this->open) {
            $this->values[$key] = serialize($value);
        }
    }
}
