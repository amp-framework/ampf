<?php

declare(strict_types=1);

namespace ampf;

class Registry
{
    /**
     * @var array<string, mixed>
     */
    protected static array $_memory = [];

    public static function set(string $key, mixed $value): void
    {
        self::$_memory[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        if (!self::has($key)) {
            return null;
        }

        return self::$_memory[$key];
    }

    public static function has(string $key): bool
    {
        return isset(self::$_memory[$key]);
    }
}
