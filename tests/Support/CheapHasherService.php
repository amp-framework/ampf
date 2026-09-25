<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Service\Hasher\HasherService;

/**
 * The hasher of an application that sets a cost of its own — bcrypt's lowest, 4.
 */
final class CheapHasherService extends HasherService
{
    protected const int COST = 4;
}
