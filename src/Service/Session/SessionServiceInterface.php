<?php

declare(strict_types=1);

namespace ampf\Service\Session;

use RuntimeException;

/** The user's session: attributes kept between the requests of one browser. */
interface SessionServiceInterface
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

    /**
     * Keeps the session's data under a new id and deletes the old session: call it where the user's privileges
     * change — at a login above all —, so an id someone knew before is worth nothing after it.
     *
     * @throws RuntimeException when the session is not open (closed already) or PHP refuses a new id
     */
    public function regenerateId(): void;

    public function removeAttribute(string $key): void;

    /**
     * Empties the session under a new id and deletes the old one, and keeps it open: what the request writes next —
     * a logout's message, say — is kept, which destroy() would lose.
     *
     * @throws RuntimeException as regenerateId()
     */
    public function renew(): void;

    public function setAttribute(string $key, mixed $value): void;
}
