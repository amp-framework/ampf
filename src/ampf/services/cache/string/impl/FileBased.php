<?php

declare(strict_types=1);

namespace ampf\services\cache\string\impl;

use ampf\services\cache\string\StringCacheService;
use RuntimeException;
use stdClass;

class FileBased implements StringCacheService
{
    protected ?string $cacheDir = null;

    protected ?int $defaultTTL = null;

    public function get(string $key): mixed
    {
        $path = $this->getPath($key);
        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);
        if (trim($content) === '') {
            unlink($path);

            return false;
        }

        $json = json_decode($content);
        if ($json === null || !is_object($json)) {
            unlink($path);

            return false;
        }

        if (!isset($json->until) || !isset($json->string)) {
            unlink($path);

            return false;
        }

        if ($json->until < time()) {
            unlink($path);

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

        $path = $this->getPath($key);
        file_put_contents($path, $content);

        return true;
    }

    /** @param array<string, mixed> $config */
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

        $cachedir = realpath($config['stringfilecache']['cachedir']);
        if (
            $cachedir === false
            || !is_dir($cachedir)
            || !is_writable($cachedir)
        ) {
            throw new RuntimeException();
        }

        $this->cacheDir = $cachedir;

        $this->defaultTTL = 3600;
        if (isset($config['stringfilecache']['defaultttl'])) {
            $this->defaultTTL = ((int)$config['stringfilecache']['defaultttl']);
        }
    }

    protected function getPath(string $key): string
    {
        if (!$this->isCorrectKey($key)) {
            throw new RuntimeException();
        }

        return $this->cacheDir . '/' . $key . '.asc';
    }

    protected function isCorrectKey(string $key): bool
    {
        return preg_match('/^[a-zA-Z0-9_\-\.]+$/', $key);
    }
}
