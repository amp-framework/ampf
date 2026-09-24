<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Service\StringCache\FileStringCacheService;

/**
 * The cache with its occasional sweep switched off, so a test decides when to sweep.
 */
final class NeverSweepingFileCache extends FileStringCacheService
{
    protected function shouldSweep(): bool
    {
        return false;
    }
}
