<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Service\Session\SessionServiceInterface;

/**
 * A session that keeps copies — its values serialized, as a session in another store keeps them: what changes after
 * setAttribute() is not kept unless it is set again.
 *
 * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
 */
final class CopyingSessionService implements SessionServiceInterface
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    public function close(): void
    {
        // Nothing to write
    }

    public function destroy(): void
    {
        $this->values = [];
    }

    public function getAttribute(string $key): mixed
    {
        return isset($this->values[$key])
            ? unserialize($this->values[$key])
            : null;
    }

    public function hasAttribute(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function regenerateId(): void
    {
        // One id serves
    }

    public function removeAttribute(string $key): void
    {
        unset($this->values[$key]);
    }

    public function renew(): void
    {
        $this->values = [];
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->values[$key] = serialize($value);
    }
}
