<?php

declare(strict_types=1);

namespace ampf\Service\StringCache;

/** A cache of strings by key (whole rendered pages, for example), each entry with a time to live. */
interface StringCacheServiceInterface
{
    /** The entry's string, false when there is none (never written, expired, damaged, or the cache is off). */
    public function get(string $key): mixed;

    /**
     * Stores the string for $ttl seconds (the configured default when null); false when it was not stored (the cache
     * is off, or it cannot hold the string).
     */
    public function set(string $key, string $string, ?int $ttl = null): bool;

    /**
     * Removes the expired entries and returns how many went: for a cron job, besides the sweeps the writes run now
     * and then on their own.
     */
    public function sweep(): int;
}
