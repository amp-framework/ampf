<?php

declare(strict_types=1);

namespace ampf\Testing\Guard;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RegexIterator;
use SplFileInfo;

/**
 * Every PHP file under the application's source directory declares the type its path names (PSR-4), in the path's exact
 * case: a wrongly cased directory or namespace loads on a case-insensitive file system and fails on any other, so it
 * fails here first. An application extends it in one class that names its project root and namespace:
 *
 *     final class ClassLoadingTest extends ClassLoadingGuard
 *     {
 *         protected static function projectRoot(): string { return dirname(__DIR__, 2); }
 *         protected static function sourceNamespace(): string { return 'App'; }
 *     }
 */
abstract class ClassLoadingGuard extends AbstractGuard
{
    /** The namespace of the source directory (PSR-4), without a backslash at either end: `App`. */
    abstract protected static function sourceNamespace(): string;

    /** The directory of the application's classes, relative to the project root. */
    protected static function sourceDirectory(): string
    {
        return 'src';
    }

    /**
     * Every PHP file under the source directory, and the type its path names, in the order the directory lists them.
     *
     * @return array<string, string> file => type
     */
    protected static function sourceFiles(): array
    {
        $directory = static::projectRoot() . '/' . static::sourceDirectory();
        $tree = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
        $files = [];

        foreach (new RegexIterator(new RecursiveIteratorIterator($tree), '/\.php\z/') as $file) {
            assert($file instanceof SplFileInfo);
            $relative = substr($file->getPathname(), strlen($directory) + 1, -strlen('.php'));
            $files[$file->getPathname()] = static::sourceNamespace() . '\\' . str_replace('/', '\\', $relative);
        }

        return $files;
    }

    public function testEveryFileDeclaresTheTypeItsPathNames(): void
    {
        $files = static::sourceFiles();
        ksort($files);
        $problems = [];

        foreach ($files as $file => $type) {
            // The autoloader loads the file once: a file that declares another type would declare it twice otherwise
            $reflection = class_exists($type) || interface_exists($type, false) || trait_exists($type, false)
                ? new ReflectionClass($type)
                : null;

            if ($reflection === null) {
                $problems[] = 'The file ' . $file . ' does not declare ' . $type . ', the type its path names.';
            } elseif ($reflection->getName() !== $type) {
                $problems[] = 'The file ' . $file . ' declares ' . $reflection->getName() . ', but its path names '
                    . $type . '.';
            } elseif ($reflection->getFileName() !== realpath($file)) {
                $problems[] = $type . ' is declared in ' . $reflection->getFileName() . ', not in ' . $file . '.';
            }
        }

        self::assertEmpty($problems, implode(PHP_EOL, $problems));
    }
}
