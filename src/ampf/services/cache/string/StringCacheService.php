<?php

declare(strict_types=1);

namespace ampf\services\cache\string;

interface StringCacheService
{
    public function get(string $key): mixed;

    public function set(string $key, string $string, ?int $ttl = null): bool;

    /**
     * Removes the expired entries and returns how many went: for a cron job, besides the sweeps the writes run now
     * and then on their own.
     */
    public function sweep(): int;
}
