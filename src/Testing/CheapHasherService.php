<?php

declare(strict_types=1);

namespace ampf\Testing;

use ampf\Service\Hasher\HasherService;

/**
 * The framework's hasher at bcrypt's lowest cost and without its random wait, for tests: what it hashes checks as any
 * bcrypt hash does, and a test that hashes a password spends no quarter of a second on it. A check that has nothing to
 * compare (a blank string, a stored value that is no bcrypt hash) verifies against a stand-in hash of the same lowest
 * cost, and is as cheap.
 */
class CheapHasherService extends HasherService
{
    /**
     * What a check with nothing to compare, and avoidTimingAttack(), verify against: a bcrypt hash of cost 4 of a text
     * nobody types. The framework's stand-in is a hash of cost 12, which makes such a check slow in a test for nothing
     * the test can tell.
     */
    protected const string TOKEN_TIMING_ATT = '$2y$04$JbqHVuwXTZjLPu9x0t.1nenr2jbZo1TboaXRYpHLGdb3inzS76Amq';

    protected const int COST = 4;

    protected function sleep(): void
    {
        // No wait: a test checks what is compared, not how long it takes
    }
}
