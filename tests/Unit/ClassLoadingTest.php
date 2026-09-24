<?php

declare(strict_types=1);

namespace ampf\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * The source tree's PSR-4 layout (`ampf\` => `src/`): every file declares the type its path names, in the path's
 * exact case — a move or a rename that missed a file fails here instead of in an application on a case-sensitive
 * file system.
 */
#[CoversNothing]
final class ClassLoadingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideSourceFiles(): iterable
    {
        $root = dirname(__DIR__, 2) . '/src';
        $files = [];

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            ) as $file
        ) {
            assert($file instanceof SplFileInfo);

            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        foreach ($files as $path) {
            $relative = substr($path, strlen($root) + 1, -strlen('.php'));

            yield $relative => [$path, 'ampf\\' . str_replace('/', '\\', $relative)];
        }
    }

    #[DataProvider('provideSourceFiles')]
    public function testTheFileDeclaresTheTypeItsPathNames(string $path, string $type): void
    {
        self::assertTrue(
            class_exists($type) || interface_exists($type) || trait_exists($type) || enum_exists($type),
            $type . ' does not load',
        );

        $reflection = new ReflectionClass($type);
        self::assertSame($type, $reflection->getName(), 'declared in the exact case of its path');
        self::assertSame(realpath($path), $reflection->getFileName());
    }
}
