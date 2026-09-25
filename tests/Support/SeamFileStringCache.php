<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Service\StringCache\FileStringCacheService;
use Random\Randomizer;

/**
 * The file cache on a clock the test sets and a draw it may seed, its protected methods noting their calls before
 * they do their work — as an application's subclass that changes one of them would.
 */
final class SeamFileStringCache extends FileStringCacheService
{
    /**
     * @var list<string>
     */
    private array $calls = [];

    public function __construct(private int $now, ?Randomizer $randomizer = null)
    {
        $this->randomizer = $randomizer;
    }

    public function setNow(int $now): void
    {
        $this->now = $now;
    }

    /**
     * The names of the protected methods called so far, each once, sorted.
     *
     * @return list<string>
     */
    public function getCalledMethods(): array
    {
        $methods = array_values(array_unique($this->calls));
        sort($methods);

        return $methods;
    }

    /** Whether a write would sweep now, drawn as a write draws it. */
    public function drawsASweep(): bool
    {
        return $this->shouldSweep();
    }

    /** The temporary file set() writes an entry's path into first. */
    public function nameTemporaryFile(string $path): string
    {
        return $this->temporaryPath($path);
    }

    protected function getCacheDir(): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::getCacheDir();
    }

    protected function getPath(string $key): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::getPath($key);
    }

    protected function isCorrectKey(string $key): bool
    {
        $this->calls[] = __FUNCTION__;

        return parent::isCorrectKey($key);
    }

    protected function readUntil(string $path): ?int
    {
        $this->calls[] = __FUNCTION__;

        return parent::readUntil($path);
    }

    protected function remove(string $path): bool
    {
        $this->calls[] = __FUNCTION__;

        return parent::remove($path);
    }

    protected function temporaryPath(string $path): string
    {
        $this->calls[] = __FUNCTION__;

        return parent::temporaryPath($path);
    }

    protected function now(): int
    {
        $this->calls[] = __FUNCTION__;

        return $this->now;
    }

    protected function randomizer(): Randomizer
    {
        $this->calls[] = __FUNCTION__;

        return parent::randomizer();
    }
}
