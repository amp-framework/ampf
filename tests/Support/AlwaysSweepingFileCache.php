<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Service\StringCache\FileStringCacheService;

/**
 * The cache sweeping at every write.
 */
final class AlwaysSweepingFileCache extends FileStringCacheService
{
    protected function shouldSweep(): bool
    {
        return true;
    }
}
