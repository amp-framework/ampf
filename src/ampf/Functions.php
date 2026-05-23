<?php

declare(strict_types=1);

namespace ampf;

use RuntimeException;

use const PREG_SPLIT_NO_EMPTY;

/**
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
abstract class Functions
{
    /**
     * @return array<string, mixed>
     */
    public static function assertStringMixedArray(mixed $array): array
    {
        if (!is_array($array)) {
            throw new RuntimeException();
        }

        $result = [];

        foreach ($array as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException();
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param ?array<mixed, mixed> $list
     *
     * @return ?array<string, string|array<mixed, mixed>>
     */
    public static function cleanGPCSLists(?array $list): ?array
    {
        if (!is_array($list)) {
            return null;
        }

        $result = [];

        foreach ($list as $key => $value) {
            if (is_scalar($value)) {
                $result[static::convertToString($key)] = static::convertToString($value);
            } elseif (is_array($value)) {
                $result[static::convertToString($key)] = $value;
            } else {
                throw new RuntimeException();
            }
        }

        return $result;
    }

    public static function convertToString(mixed $var): string
    {
        if (is_string($var)) {
            return $var;
        }

        if (is_scalar($var)) {
            return (string)$var;
        }

        throw new RuntimeException();
    }

    /**
     * @return list<mixed>
     */
    public static function decodeJSONArray(string $json): array
    {
        $array = json_decode($json);

        if ($array === null || !is_array($array) || count($array) < 1) {
            return [];
        }

        foreach ($array as $key) {
            if (!is_string($key)) {
                throw new RuntimeException();
            }
        }

        return array_values($array);
    }

    /**
     * @return list<string>
     */
    public static function mb_str_split(string $str, int $l = 0): array
    {
        if ($l > 0) {
            $ret = [];
            $len = mb_strlen($str);

            for ($i = 0; $i < $len; $i += $l) {
                $ret[] = mb_substr($str, $i, $l);
            }

            return $ret;
        }

        $result = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);

        if ($result === false) {
            throw new RuntimeException();
        }

        return $result;
    }

    public static function mb_ucfirst(string $string): string
    {
        if (trim($string) === '') {
            return $string;
        }

        $first = mb_strtoupper(mb_substr($string, 0, 1));

        return $first . mb_substr($string, 1);
    }
}
