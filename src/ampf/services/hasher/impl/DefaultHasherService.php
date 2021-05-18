<?php

declare(strict_types=1);

namespace ampf\services\hasher\impl;

/**
 * @phpcs:disable PSR12.Files.FileHeader.IncorrectGrouping
 * @phpcs:disable PSR12.Files.FileHeader.SpacingAfterBlock
 * @phpcs:disable SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses.IncorrectlyOrderedUses
 */

use ampf\services\hasher\HasherService;
use const PASSWORD_BCRYPT;
use RuntimeException;

class DefaultHasherService implements HasherService
{
    protected const TOKEN_TIMING_ATT = '$2y$12$7bXzdUEuvvooZkWPLBbTCux4VdVOJfTv2uLCS2ysoHhDOgVFRE3Q2';

    public function avoidTimingAttack(string $input): void
    {
        // Burn some CPU time by doing an useless check
        $this->check($input, static::TOKEN_TIMING_ATT);
    }

    public function check(string $string, string $storedHash): bool
    {
        if (trim($string) === '') {
            throw new RuntimeException('String to check needs to be not-blank.');
        }

        if (strlen($storedHash) !== 60) {
            throw new RuntimeException('No valid bcrypt hash given.');
        }

        // Randomly sleep some milliseconds
        $this->sleep();

        return password_verify($string, $storedHash);
    }

    public function hash(string $string): string
    {
        if (trim($string) === '') {
            throw new RuntimeException();
        }

        // Randomly sleep some milliseconds
        $this->sleep();

        $hash = password_hash($string, PASSWORD_BCRYPT, ['cost' => '12']);
        if (mb_strlen($hash) !== 60) {
            throw new RuntimeException();
        }

        return $hash;
    }

    /**
     * Sleeps randomly between 1 and 5 milliseconds to avoid timing attacks
     * and to mask the real runtime of the HasherService.
     */
    protected function sleep(): void
    {
        usleep(
            mt_rand(
                (1 * 1000),
                (5 * 1000),
            ),
        );
    }
}
