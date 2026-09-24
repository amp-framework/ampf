<?php

declare(strict_types=1);

namespace ampf\Bootstrap;

use InvalidArgumentException;

/**
 * PHP's error handling for an entry point. Before the configuration is loaded, applyDefaults() reports every error,
 * displays none and logs them all — the safe posture, under which an error in a configuration file still reaches the
 * server's error log. Once the configuration is merged, apply() follows its `errors` block.
 */
class ErrorSettings
{
    /** Every error reported, none displayed, every one logged. */
    public static function applyDefaults(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    }

    /**
     * The configuration's `errors` block: `display` (default false) shows errors in the response or on the terminal,
     * `log` (default true) writes them to PHP's error log, and `log-file` (optional: a path relative to $projectRoot,
     * or absolute) names that log's file.
     *
     * @param array<string, mixed> $config the merged configuration
     *
     * @throws InvalidArgumentException for a block or a setting of another type
     */
    public static function apply(array $config, string $projectRoot): void
    {
        $errors = $config['errors'] ?? [];

        if (!is_array($errors)) {
            throw new InvalidArgumentException(
                'The configuration\'s errors must be an array, not ' . get_debug_type($errors) . '.',
            );
        }

        ini_set('display_errors', self::flag($errors, 'display', false) ? '1' : '0');
        ini_set('log_errors', self::flag($errors, 'log', true) ? '1' : '0');

        $logFile = $errors['log-file'] ?? null;

        if ($logFile === null) {
            return;
        }

        if (!is_string($logFile) || $logFile === '') {
            throw new InvalidArgumentException('The configuration\'s errors.log-file must name a file.');
        }

        ini_set('error_log', str_starts_with($logFile, '/') ? $logFile : rtrim($projectRoot, '/') . '/' . $logFile);
    }

    /**
     * @param array<mixed> $errors
     */
    private static function flag(array $errors, string $key, bool $default): bool
    {
        $value = $errors[$key] ?? $default;

        if (!is_bool($value)) {
            throw new InvalidArgumentException('The configuration\'s errors.' . $key . ' must be true or false.');
        }

        return $value;
    }
}
