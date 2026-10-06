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

    public function testACheckWithNothingToCompareVerifiesAgainstAStandInOfBcryptsLowestCost(): void
    {
        $hasher = new class extends CheapHasherService {
            /**
             * @var list<string>
             */
            private array $verified = [];

            /**
             * The hashes the verifications compared with, in their order.
             *
             * @return list<string>
             */
            public function getVerifiedHashes(): array
            {
                return $this->verified;
            }

            protected function verify(string $string, string $hash): bool
            {
                $this->verified[] = $hash;

                return parent::verify($string, $hash);
            }
        };

        self::assertFalse($hasher->check(" \t\n", $hasher->hash('secret')), 'a blank string');
        self::assertFalse($hasher->check('secret', 'not a bcrypt hash'), 'a stored value that is no bcrypt hash');
        $hasher->avoidTimingAttack('secret');

        $verified = $hasher->getVerifiedHashes();
        self::assertCount(3, $verified, 'each took the time of one verification');
        self::assertCount(1, array_unique($verified), 'against one stand-in: the one avoidTimingAttack() uses');

        $standIn = $verified[0];
        $info = password_get_info($standIn);

        self::assertSame('bcrypt', $info['algoName']);
        self::assertSame(4, $info['options']['cost'], 'the cost of the hasher\'s own hashes, not the framework\'s');
        self::assertSame(
            60,
            strlen(crypt('a text that is not the one', $standIn)),
            'its salt and its cost are accepted, so that a verification runs in full',
        );
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
