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

    /**
     * @return list<mixed>
     */
    public static function decodeJSONArray(string $json): array
    {
        $array = json_decode($json);

        if ($array === null || !is_array($array) || count($array) < 1) {
            return [];
        }

        return $array;
    }
}
