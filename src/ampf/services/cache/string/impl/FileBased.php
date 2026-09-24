<?php

declare(strict_types=1);

namespace ampf\services\cache\string\impl;

use ampf\services\cache\string\StringCacheService;
use RuntimeException;
use stdClass;

/**
 * A string cache of one file per key (`<key>.asc` in the configured directory). A write replaces the file at once
 * (a temporary file in the same directory, renamed over it), so a reader never sees half an entry; and one write
 * in SWEEP_EVERY sweeps the expired entries away. The sweep touches its own entries only — the directory may hold
 * other things.
 */
class FileBased implements StringCacheService
{
    /**
     * One write in this many sweeps the expired entries (on average).
     */
    protected const int SWEEP_EVERY = 100;

    /**
     * An abandoned temporary file (its writer died before the rename) older than this many seconds is swept too.
     */
    protected const int TEMPORARY_FILE_AGE = 3_600;

    protected ?string $cacheDir = null;

    protected ?int $defaultTTL = null;

    public function get(string $key): mixed
    {
        $path = $this->getPath($key);

        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            $this->remove($path);

            return false;
        }

        $json = json_decode($content);

        if ($json === null || !is_object($json)) {
            $this->remove($path);

            return false;
        }

        if (!isset($json->until) || !isset($json->string)) {
            $this->remove($path);

            return false;
        }

        if ($json->until < time()) {
            $this->remove($path);

            return false;
        }

        return $json->string;
    }

    public function set(string $key, string $string, ?int $ttl = null): bool
    {
        if (trim($string) === '') {
            throw new RuntimeException();
        }

        if ($ttl === null) {
            $ttl = $this->defaultTTL;
        }

        $json = new stdClass();
        $json->until = (time() + $ttl);
        $json->string = $string;

        $content = json_encode($json);

        // A string JSON cannot carry (not UTF-8) is not cached
        if ($content === false) {
            return false;
        }

        $path = $this->getPath($key);

        // The whole entry under a name of its own first, then renamed over the old one in one step
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';

        if (file_put_contents($temporary, $content) !== strlen($content) || !rename($temporary, $path)) {
            $this->remove($temporary);

            throw new RuntimeException('Could not write the cache entry ' . $key . '.');
        }

        if ($this->shouldSweep()) {
            $this->sweep();
        }

        return true;
    }

    /**
     * Removes the entries whose time is up (read from the head of each file, where set() writes it), entries that
     * are no entry any more (empty or damaged: get() would remove them too), and temporary files a writer left
     * behind; nothing else in the directory.
     */
    public function sweep(): int
    {
        $directory = $this->getCacheDir();
        $names = scandir($directory);
        $removed = 0;
        $now = time();

        foreach ($names === false ? [] : $names as $name) {
            $path = $directory . '/' . $name;

            if (str_ends_with($name, '.asc') && $this->isCorrectKey(substr($name, 0, -4)) && is_file($path)) {
                $until = $this->readUntil($path);
                $expired = ($until === null || $until < $now);
            } elseif (preg_match('/^[a-zA-Z0-9_\-\.]+\.asc\.[0-9a-f]{16}\.tmp$/D', $name) === 1 && is_file($path)) {
                $modified = filemtime($path);
                $expired = ($modified !== false && $modified < ($now - static::TEMPORARY_FILE_AGE));
            } else {
                continue;
            }

            if ($expired && $this->remove($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        if (count($config) < 1) {
            throw new RuntimeException();
        }

        if (!isset($config['stringfilecache']) || !is_array($config['stringfilecache'])) {
            throw new RuntimeException();
        }

        if (!isset($config['stringfilecache']['cachedir'])) {
            throw new RuntimeException();
        }

        $cachedir = $config['stringfilecache']['cachedir'];

        if (!is_string($cachedir)) {
            throw new RuntimeException();
        }

        $cachedir = realpath($cachedir);

        if (
            $cachedir === false
            || !is_dir($cachedir)
            || !is_writable($cachedir)
        ) {
            throw new RuntimeException();
        }

        $this->cacheDir = $cachedir;

        $this->defaultTTL = 3_600;

        // @phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if (isset($config['stringfilecache']['defaultttl'])) {
            $defaultttl = $config['stringfilecache']['defaultttl'];

            if (!is_scalar($defaultttl)) {
                throw new RuntimeException();
            }

            $this->defaultTTL = ((int)$defaultttl);
        }
    }

    protected function getCacheDir(): string
    {
        if ($this->cacheDir === null) {
            throw new RuntimeException('The string cache has no directory.');
        }

        return $this->cacheDir;
    }

    protected function getPath(string $key): string
    {
        if (!$this->isCorrectKey($key)) {
            throw new RuntimeException();
        }

        return $this->getCacheDir() . '/' . $key . '.asc';
    }

    protected function isCorrectKey(string $key): bool
    {
        return preg_match('/^[a-zA-Z0-9_\-\.]+$/D', $key) === 1;
    }

    /**
     * The expiry time at the head of an entry (`{"until":<time>,`), null when the file does not start like one.
     */
    protected function readUntil(string $path): ?int
    {
        $head = file_get_contents($path, false, null, 0, 64);

        if (!is_string($head) || preg_match('/^\{"until":(\d{1,19}),/', $head, $matches) !== 1) {
            return null;
        }

        return (int)$matches[1];
    }

    /** Removes the file; false when it was gone already (another request swept it) or could not be removed. */
    protected function remove(string $path): bool
    {
        clearstatcache(true, $path);

        return is_file($path) && unlink($path);
    }

    /** Whether this write sweeps: one in SWEEP_EVERY, at random. */
    protected function shouldSweep(): bool
    {
        return random_int(1, static::SWEEP_EVERY) === 1;
    }
}
