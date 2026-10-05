<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing;

use ampf\Service\Hasher\HasherService;
use ampf\Testing\CheapHasherService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheapHasherService::class)]
final class CheapHasherServiceTest extends TestCase
{
    public function testItHashesAtBcryptsLowestCost(): void
    {
        $hasher = new CheapHasherService();
        $hash = $hasher->hash('correct horse');

        self::assertStringStartsWith('$2y$04$', $hash);
        self::assertTrue($hasher->check('correct horse', $hash));
        self::assertFalse($hasher->check('correct horsf', $hash));
        self::assertFalse($hasher->needsRehash($hash));
        self::assertTrue(
            $hasher->needsRehash(new HasherService()->hash('correct horse')),
            'the framework\'s cost is not its cost',
        );
    }

    public function testWhatItHashesIsWhatTheFrameworksHasherAccepts(): void
    {
        self::assertTrue(new HasherService()->check('secret', new CheapHasherService()->hash('secret')));
    }

    public function testItDoesNotWait(): void
    {
        $hasher = new class extends CheapHasherService {
            public function waits(): bool
            {
                $start = hrtime(true);
                $this->sleep();

                return hrtime(true) - $start >= 1_000_000;
            }
        };

        // The framework's hasher waits a millisecond at least; this one returns at once, every time
        for ($call = 0; $call < 20; $call++) {
            self::assertFalse($hasher->waits());
        }
    }
}
