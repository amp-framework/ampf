<?php

declare(strict_types=1);

namespace ampf\services\session;

interface SessionService
{
    public function destroy(): void;

    public function getAttribute(string $key): mixed;

    public function hasAttribute(string $key): bool;

    public function removeAttribute(string $key): void;

    public function setAttribute(string $key, mixed $value): void;
}
