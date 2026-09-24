<?php

declare(strict_types=1);

namespace ampfTest\Support;

use ampf\services\hasher\impl\DefaultHasherService;

/**
 * The real hasher, counting the expensive verifications it runs (the time a check takes).
 */
final class CountingHasherService extends DefaultHasherService
{
    private int $verifications = 0;

    public function getVerifications(): int
    {
        return $this->verifications;
    }

    protected function verify(string $string, string $hash): bool
    {
        $this->verifications++;

        return parent::verify($string, $hash);
    }
}
