<?php

declare(strict_types=1);

namespace ampf\Testing;

use ampf\Service\Hasher\HasherService;

/**
 * The framework's hasher at bcrypt's lowest cost and without its random wait, for tests: what it hashes checks as any
 * bcrypt hash does, and a test that hashes a password spends no quarter of a second on it.
 */
class CheapHasherService extends HasherService
{
    protected const int COST = 4;

    protected function sleep(): void
    {
        // No wait: a test checks what is compared, not how long it takes
    }
}
