<?php

declare(strict_types=1);

namespace ampf\services\cache\string;

interface StringCacheService
{
    public function get(string $key): mixed;

    public function set(string $key, string $string, ?int $ttl = null): bool;
}
