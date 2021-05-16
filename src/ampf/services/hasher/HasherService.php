<?php

declare(strict_types=1);

namespace ampf\services\hasher;

interface HasherService
{
    public function avoidTimingAttack(string $input): void;

    public function check(string $string, string $storedHash): bool;

    public function hash(string $string): string;
}
