<?php

declare(strict_types=1);

namespace ampf\services\hasher\impl;

/**
 * @phpcs:disable PSR12.Files.FileHeader.IncorrectGrouping
 *
 * @phpcs:disable PSR12.Files.FileHeader.SpacingAfterBlock
 *
 * @phpcs:disable SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses.IncorrectlyOrderedUses
 */

use ampf\services\hasher\HasherService;
use InvalidArgumentException;
use RuntimeException;

use const PASSWORD_BCRYPT;

class DefaultHasherService implements HasherService
{
    protected const string TOKEN_TIMING_ATT = '$2y$12$7bXzdUEuvvooZkWPLBbTCux4VdVOJfTv2uLCS2ysoHhDOgVFRE3Q2';

    /**
     * The bcrypt cost of a new hash; a stored hash of another cost needs a rehash.
     */
    protected const int COST = 12;

    public function avoidTimingAttack(string $input): void
    {
        // Burn some CPU time by doing an useless check
        $this->check($input, static::TOKEN_TIMING_ATT);
    }

    public function check(string $string, string $storedHash): bool
    {
        // Randomly sleep some milliseconds
        $this->sleep();

        // Nothing to compare: the time of a check all the same, and the answer of a wrong string
        if (trim($string) === '' || !$this->isBcryptHash($storedHash)) {
            $this->verify($string, static::TOKEN_TIMING_ATT);

            return false;
        }

        return $this->verify($string, $storedHash);
    }

    public function hash(string $string): string
    {
        if (trim($string) === '') {
            throw new RuntimeException('String to hash needs to be not-blank.');
        }

        // bcrypt cannot hash past a NUL byte (password_hash() throws a ValueError); no browser sends one in a form
        if (str_contains($string, "\0")) {
            throw new InvalidArgumentException('String to hash must not contain a NUL byte.');
        }

        // Randomly sleep some milliseconds
        $this->sleep();

        $hash = password_hash($string, PASSWORD_BCRYPT, ['cost' => static::COST]);

        if (strlen($hash) !== 60) {
            throw new RuntimeException();
        }

        return $hash;
    }

    public function needsRehash(string $storedHash): bool
    {
        return password_needs_rehash($storedHash, PASSWORD_BCRYPT, ['cost' => static::COST]);
    }

    /**
     * Whether the value has a bcrypt hash's shape: `$2y$`, a cost of two digits, 53 characters of salt and hash.
     */
    protected function isBcryptHash(string $value): bool
    {
        return preg_match('~^\$2[abxy]\$(0[4-9]|[12][0-9]|3[01])\$[./A-Za-z0-9]{53}\z~', $value) === 1;
    }

    /**
     * Sleeps randomly between 1 and 5 milliseconds to avoid timing attacks
     * and to mask the real runtime of the HasherService.
     */
    protected function sleep(): void
    {
        usleep(
            mt_rand(
                (1 * 1_000),
                (5 * 1_000),
            ),
        );
    }

    /**
     * The one place a string is verified against a hash (the expensive part).
     */
    protected function verify(string $string, string $hash): bool
    {
        return password_verify($string, $hash);
    }
}
