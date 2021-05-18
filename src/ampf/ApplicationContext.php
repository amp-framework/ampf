<?php

declare(strict_types=1);

namespace ampf;

class ApplicationContext
{
    /**
     * @param ?string[] $configFiles
     *
     * @return array<string, mixed>
     */
    public static function boot(?array $configFiles = null): array
    {
        $config = [];
        if ($configFiles !== null) {
            foreach ($configFiles as $configFile) {
                $config = self::mergeConfig($config, require $configFile);
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
            if (!isset($config2[$key])) {
                $result[$key] = $value;
                unset($config2[$key]);
            } else {
                // If the value is an array, recurse one level deep
                if (is_array($value) && $depth === 0) {
                    $result[$key] = static::mergeConfig($value, $config2[$key], ($depth + 1));
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
