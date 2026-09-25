<?php

declare(strict_types=1);

namespace ampf\Bootstrap;

use ampf\Helper\Functions;
use RuntimeException;

/**
 * Loads the configuration: every file returns an array, and the arrays are merged in the order given, one level
 * deep — a later file adds or replaces top-level keys, and inside a top-level array (`beans`, `routes`, `doctrine`,
 * …) adds or replaces entries, each entry (one bean, one route, a nested block) as a whole.
 */
class ApplicationContext
{
    /**
     * The merged configuration of the files. Each file is executable PHP, `require`d in a scope of its own: its
     * variables are its own, and it cannot see the configuration merged so far.
     *
     * @param ?list<string> $configFiles
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when a file returns no array keyed by strings, or sets a key to a value that cannot
     *     be merged with an earlier file's array
     */
    public static function boot(?array $configFiles = null): array
    {
        $config = [];

        foreach ($configFiles ?? [] as $configFile) {
            $fileConfig = static::load($configFile);

            try {
                Functions::assertStringMixedArray($fileConfig);
            } catch (RuntimeException $e) {
                throw new RuntimeException(
                    'The configuration file ' . $configFile . ': ' . $e->getMessage(),
                    previous: $e,
                );
            }

            $config = static::mergeConfig($config, $fileConfig);
        }

        return $config;
    }

    /**
     * What a configuration file returns, the file run in a scope that holds nothing but its own name.
     */
    protected static function load(string $configFile): mixed
    {
        return (static fn (string $__file): mixed => require $__file)($configFile);
    }

    /**
     * Merges config2 over config1: a key only one of them has keeps its value, a key both have takes config2's —
     * except a block, an array under a top-level key, whose own keys are merged the same way.
     *
     * @param array<string, mixed> $config1
     * @param array<string, mixed> $config2
     * @param bool $blocks whether an array under these keys is a block (at the top level) or a value (inside one)
     *
     * @return array<string, mixed>
     */
    protected static function mergeConfig(array $config1, array $config2, bool $blocks = true): array
    {
        $result = [];

        foreach ($config1 as $key => $value) {
            if (!array_key_exists($key, $config2)) {
                // Only config1 has the key: its value stays
                $result[$key] = $value;

                continue;
            }

            // Both have the key: a block is merged, anything else is config2's value
            $result[$key] = $blocks && is_array($value)
                ? static::mergeConfig(static::block($key, $value), static::block($key, $config2[$key]), false)
                : $config2[$key];
            unset($config2[$key]);
        }

        // Copy all remaining entries from config2 to config1
        foreach ($config2 as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * A top-level block that two files define, which must be an array keyed by strings in both.
     *
     * @return array<string, mixed>
     */
    protected static function block(string $key, mixed $value): array
    {
        try {
            Functions::assertStringMixedArray($value);
        } catch (RuntimeException $e) {
            throw new RuntimeException(
                'The configuration\'s ' . $key . ' cannot be merged: ' . $e->getMessage(),
                previous: $e,
            );
        }

        return $value;
    }
}
