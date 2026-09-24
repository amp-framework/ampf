<?php

declare(strict_types=1);

namespace ampf\services\session;

interface SessionService
{
    /**
     * Writes the session and releases its lock, for a request that runs long and needs nothing more from the
     * session than it has read: the user's other requests no longer wait for it. What the request reads afterwards
     * is what the session held; what it writes afterwards is not kept.
     */
    public function close(): void;

    public function destroy(): void;

    public function getAttribute(string $key): mixed;

    public function hasAttribute(string $key): bool;

    public function removeAttribute(string $key): void;

    public function setAttribute(string $key, mixed $value): void;
}
