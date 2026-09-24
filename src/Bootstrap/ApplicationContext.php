<?php

declare(strict_types=1);

namespace ampf\Bootstrap;

use ampf\Helper\Functions;

/**
 * Loads the configuration: every file returns an array, and the arrays are merged in the order given, one level
 * deep — a later file adds or replaces top-level keys, and inside a top-level array (`beans`, `routes`, `doctrine`,
 * …) adds or replaces entries, each entry (one bean, one route, a nested block) as a whole.
 */
class ApplicationContext
{
    /**
     * The merged configuration of the files. They are executable PHP, `require`d in this method's scope: a file must
     * not assign a variable named `$config`.
     *
     * @param ?list<string> $configFiles
     *
     * @return array<string, mixed>
     */
    public static function boot(?array $configFiles = null): array
    {
        $config = [];

        if ($configFiles !== null) {
            foreach ($configFiles as $configFile) {
                $config2 = require $configFile;
                Functions::assertStringMixedArray($config2);

                $config = self::mergeConfig($config, $config2);
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config1
     * @param array<string, mixed> $config2
     *
     * @return array<string, mixed>
     */
    protected static function mergeConfig(array $config1, array $config2, int $depth = 0): array
    {
        $result = [];

        foreach ($config1 as $key => $value) {
            // If config2 has no such entry, just take entry from config1
            if (!array_key_exists($key, $config2)) {
                $result[$key] = $value;
            } else {
                // If the value is an array, recurse one level deep
                if (is_array($value) && $depth === 0) {
                    Functions::assertStringMixedArray($value);

                    // The value from config2 also needs to be an array
                    $config2Value = $config2[$key];
                    Functions::assertStringMixedArray($config2Value);

                    $result[$key] = static::mergeConfig($value, $config2Value, ($depth + 1));
                } else { // Else just take over the value from config2
                    $result[$key] = $config2[$key];
                }

                unset($config2[$key]);
            }
        }

        // Copy all remaining entries from config2 to config1
        foreach ($config2 as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }
}
