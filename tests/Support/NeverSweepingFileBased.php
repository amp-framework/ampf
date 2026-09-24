<?php

declare(strict_types=1);

namespace ampfTest\Support;

use ampf\services\cache\string\impl\FileBased;

/**
 * The cache with its occasional sweep switched off, so a test decides when to sweep.
 */
final class NeverSweepingFileBased extends FileBased
{
    protected function shouldSweep(): bool
    {
        return false;
    }
}
