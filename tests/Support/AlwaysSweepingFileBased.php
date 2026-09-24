<?php

declare(strict_types=1);

namespace ampfTest\Support;

use ampf\services\cache\string\impl\FileBased;

/**
 * The cache sweeping at every write.
 */
final class AlwaysSweepingFileBased extends FileBased
{
    protected function shouldSweep(): bool
    {
        return true;
    }
}
