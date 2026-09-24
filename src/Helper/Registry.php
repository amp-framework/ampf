<?php

declare(strict_types=1);

namespace ampf\Helper;

/**
 * A process-wide key-value store. Prefer beans: what a bean factory holds belongs to one request scope, what this
 * class holds to the whole process.
 */
class Registry
{
    /**
     * @var array<string, mixed>
     */
    protected static array $memory = [];

    public static function set(string $key, mixed $value): void
    {
        self::$memory[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        if (!self::has($key)) {
            return null;
        }

        return self::$memory[$key];
    }

    public static function has(string $key): bool
    {
        return isset(self::$memory[$key]);
    }
}
