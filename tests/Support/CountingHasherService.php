<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Service\Hasher\HasherService;

/**
 * The real hasher, noting what its protected methods do before they do it: the hashes it verifies against (the
 * expensive part), the values it takes for bcrypt hashes, and how long each of its random waits took.
 */
final class CountingHasherService extends HasherService
{
    /**
     * @var list<string>
     */
    private array $verifiedHashes = [];

    /**
     * @var list<string>
     */
    private array $checkedShapes = [];

    /**
     * @var list<int>
     */
    private array $sleeps = [];

    public function getVerifications(): int
    {
        return count($this->verifiedHashes);
    }

    /**
     * The hashes each verification compared with, in their order.
     *
     * @return list<string>
     */
    public function getVerifiedHashes(): array
    {
        return $this->verifiedHashes;
    }

    /**
     * The stored values checked for a bcrypt hash's shape, in their order.
     *
     * @return list<string>
     */
    public function getCheckedShapes(): array
    {
        return $this->checkedShapes;
    }

    /**
     * The time each wait took, in nanoseconds.
     *
     * @return list<int>
     */
    public function getSleeps(): array
    {
        return $this->sleeps;
    }

    protected function verify(string $string, string $hash): bool
    {
        $this->verifiedHashes[] = $hash;

        return parent::verify($string, $hash);
    }

    protected function isBcryptHash(string $value): bool
    {
        $this->checkedShapes[] = $value;

        return parent::isBcryptHash($value);
    }

    protected function sleep(): void
    {
        $start = hrtime(true);

        parent::sleep();

        $this->sleeps[] = hrtime(true) - $start;
    }
}
