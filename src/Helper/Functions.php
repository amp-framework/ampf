<?php

declare(strict_types=1);

namespace ampf\Helper;

use RuntimeException;

/**
 * Static helpers: the type checks of the configuration and of the request's input, and the decoding of a JSON list
 * of strings.
 */
abstract class Functions
{
    /**
     * @phpstan-assert array<string, mixed> $array
     *
     * @throws RuntimeException when the value is no array, or an array with a key that is no string
     */
    public static function assertStringMixedArray(mixed $array): void
    {
        if (!is_array($array)) {
            throw new RuntimeException('Expected an array keyed by strings, got ' . get_debug_type($array) . '.');
        }

        foreach (array_keys($array) as $key) {
            if (!is_string($key)) {
                throw new RuntimeException('Expected an array keyed by strings, found the key ' . $key . '.');
            }
        }
    }

    /**
     * One of PHP's input arrays (`$_GET`, `$_POST`, `$_COOKIE`, `$_SERVER`) with every scalar as its text and every
     * array as it is; null for null. PHP turns a key of digits into an int again, so such a key stays one.
     *
     * @param ?array<mixed, mixed> $list
     *
     * @return ?array<string, string|array<mixed, mixed>>
     *
     * @throws RuntimeException for a value that is neither a scalar nor an array
     */
    public static function cleanGPCSLists(?array $list): ?array
    {
        if ($list === null) {
            return null;
        }

        $result = [];

        foreach ($list as $key => $value) {
            if (is_scalar($value)) {
                $result[static::convertToString($key)] = static::convertToString($value);
            } elseif (is_array($value)) {
                $result[static::convertToString($key)] = $value;
            } else {
                throw new RuntimeException(
                    'The input ' . $key . ' is neither a scalar nor an array, but ' . get_debug_type($value) . '.',
                );
            }
        }

        return $result;
    }

    /**
     * A scalar as its text (a bool as "1" or "").
     *
     * @throws RuntimeException for anything that is no scalar
     */
    public static function convertToString(mixed $var): string
    {
        if (is_string($var)) {
            return $var;
        }

        if (is_scalar($var)) {
            return (string)$var;
        }

        throw new RuntimeException('Expected a scalar, got ' . get_debug_type($var) . '.');
    }

    /**
     * The strings of a JSON array; an empty list for anything that is no JSON array (an object, a scalar, invalid
     * JSON) and for an empty one.
     *
     * @return list<string>
     *
     * @throws RuntimeException when the array holds a value that is no string
     */
    public static function decodeJSONArray(string $json): array
    {
        $array = json_decode($json);

        if (!is_array($array)) {
            return [];
        }

        $strings = [];

        foreach ($array as $value) {
            if (!is_string($value)) {
                throw new RuntimeException('Expected a JSON array of strings, found ' . get_debug_type($value) . '.');
            }

            $strings[] = $value;
        }

        return $strings;
    }
}
