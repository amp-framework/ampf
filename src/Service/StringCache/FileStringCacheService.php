<?php

declare(strict_types=1);

namespace ampf\Service\StringCache;

use Random\Randomizer;
use RuntimeException;

/**
 * A string cache of one file per key (`<key>.asc` in the configured directory). A write replaces the file at once
 * (a temporary file in the same directory, renamed over it), so a reader never sees half an entry; and one write
 * in SWEEP_EVERY sweeps the expired entries away. The sweep touches its own entries only — the directory may hold
 * other things. The configuration may switch the cache off (`stringfilecache.enabled` false — a development machine,
 * where a cached page hides a change): it then stores nothing and serves nothing, not even an entry that is there.
 */
class FileStringCacheService implements StringCacheServiceInterface
{
    /**
     * One write in this many sweeps the expired entries (on average).
     */
    protected const int SWEEP_EVERY = 100;

    /**
     * An abandoned temporary file (its writer died before the rename) older than this many seconds is swept too.
     */
    protected const int TEMPORARY_FILE_AGE = 3_600;

    /**
     * The bytes of an entry's head sweep() reads: enough for `{"until":`, the time of any entry and its comma.
     */
    protected const int HEAD_LENGTH = 64;

    protected ?string $cacheDir = null;

    protected ?int $defaultTTL = null;

    protected bool $enabled = true;

    /**
     * The draw of the sweeps; PHP's secure one unless a subclass (a test's) sets another.
     */
    protected ?Randomizer $randomizer = null;

    public function get(string $key): mixed
    {
        if (!$this->enabled) {
            return false;
        }

        $path = $this->getPath($key);
        // @: an entry that is not there — none yet, or removed by another request meanwhile — is none, not a warning
        $content = @file_get_contents($path);

        if ($content === false) {
            return false;
        }

        $json = json_decode($content);

        // A damaged entry, or one whose time is up
        if (
            !is_object($json)
            || !isset($json->until, $json->string)
            || !is_int($json->until)
            || !is_string($json->string)
            || $json->until < $this->now()
        ) {
            $this->remove($path);

            return false;
        }

        return $json->string;
    }

    public function set(string $key, string $string, ?int $ttl = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (trim($string) === '') {
            throw new RuntimeException('A blank string is no cache entry: get() could not tell it from none.');
        }

        $content = json_encode(['until' => $this->now() + ($ttl ?? $this->defaultTTL), 'string' => $string]);

        // A string JSON cannot carry (not UTF-8) is not cached
        if ($content === false) {
            return false;
        }

        $path = $this->getPath($key);

        // The whole entry under a name of its own first, then renamed over the old one in one step
        $temporary = $this->temporaryPath($path);

        // @: a failure is this method's exception, not a warning besides it
        if (@file_put_contents($temporary, $content) !== strlen($content) || !@rename($temporary, $path)) {
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
        $now = $this->now();

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
     *
     * @throws RuntimeException for a block without a writable directory, or with a time to live that is no number
     */
    public function setConfig(array $config): void
    {
        $block = $config['stringfilecache'] ?? null;

        if (!is_array($block)) {
            throw new RuntimeException(
                'The configuration\'s stringfilecache must be an array, not ' . get_debug_type($block) . '.',
            );
        }

        $cacheDir = $block['cachedir'] ?? null;

        if (!is_string($cacheDir)) {
            throw new RuntimeException(
                'The configuration\'s stringfilecache.cachedir must name a directory, not ' . get_debug_type(
                    $cacheDir,
                ) . '.',
            );
        }

        $realPath = realpath($cacheDir);

        if ($realPath === false || !is_dir($realPath) || !is_writable($realPath)) {
            throw new RuntimeException(
                'The cache directory ' . $cacheDir . ' is no directory this process can write to.',
            );
        }

        $defaultTTL = $block['defaultttl'] ?? 3_600;

        if (!is_numeric($defaultTTL)) {
            throw new RuntimeException(
                'The configuration\'s stringfilecache.defaultttl must be a number of seconds, not '
                . get_debug_type($defaultTTL) . '.',
            );
        }

        $this->cacheDir = $realPath;
        $this->defaultTTL = (int)$defaultTTL;
        // On unless the configuration says false
        $this->enabled = ($block['enabled'] ?? true) !== false;
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
            throw new RuntimeException(
                'The cache key ' . $key . ' has a character other than letters, digits and _.-.',
            );
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
        $head = file_get_contents($path, length: self::HEAD_LENGTH);

        if (!is_string($head) || preg_match('/^\{"until":(\d{1,19}),/', $head, $matches) !== 1) {
            return null;
        }

        return (int)$matches[1];
    }

    /** Removes the file; false when it was gone already (another request swept it) or could not be removed. */
    protected function remove(string $path): bool
    {
        // @: a file another request removed first is no error
        return @unlink($path);
    }

    /** Whether this write sweeps: one in SWEEP_EVERY, at random. */
    protected function shouldSweep(): bool
    {
        return $this->randomizer()->getInt(1, static::SWEEP_EVERY) === 1;
    }

    /** The entry's temporary file while it is written: its path, a dot, 16 random hex characters and `.tmp`. */
    protected function temporaryPath(string $path): string
    {
        return $path . '.' . bin2hex($this->randomizer()->getBytes(8)) . '.tmp';
    }

    /** The time an entry's expiry is measured against: now, in Unix seconds. */
    protected function now(): int
    {
        return time();
    }

    protected function randomizer(): Randomizer
    {
        return $this->randomizer ??= new Randomizer();
    }
}
