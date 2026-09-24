<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A directory of a test's own under the system's temporary directory, removed with everything in it.
 */
final readonly class TemporaryDirectory
{
    private string $path;

    public function __construct(string $prefix)
    {
        $this->path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6));
        mkdir($this->path, 0o755, true);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** Copies the directory's contents into this one, below the relative path. */
    public function copy(string $directory, string $to = ''): string
    {
        $target = $this->path . ($to === '' ? '' : '/' . $to);

        if (!is_dir($target)) {
            mkdir($target, 0o755, true);
        }

        $sources = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($sources as $source) {
            assert($source instanceof SplFileInfo);
            $copy = $target . '/' . substr($source->getPathname(), strlen($directory) + 1);

            if ($source->isDir()) {
                mkdir($copy);
            } else {
                copy($source->getPathname(), $copy);
            }
        }

        return $target;
    }

    public function remove(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);

            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->path);
    }
}
