<?php

declare(strict_types=1);

namespace ampf\Service\Hasher;

/**
 * @phpcs:disable PSR12.Files.FileHeader.IncorrectGrouping
 *
 * @phpcs:disable PSR12.Files.FileHeader.SpacingAfterBlock
 *
 * @phpcs:disable SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses.IncorrectlyOrderedUses
 */

use InvalidArgumentException;
use RuntimeException;

use const PASSWORD_BCRYPT;

/**
 * bcrypt at cost 12 through PHP's password API; a check that has nothing to compare (a blank string, a stored value
 * that is no bcrypt hash) still spends the time of one, and every call sleeps 1 to 5 milliseconds at random.
 */
class HasherService implements HasherServiceInterface
{
    protected const string TOKEN_TIMING_ATT = '$2y$12$7bXzdUEuvvooZkWPLBbTCux4VdVOJfTv2uLCS2ysoHhDOgVFRE3Q2';

    /**
     * The bcrypt cost of a new hash; a stored hash of another cost needs a rehash.
     */
    protected const int COST = 12;

    /**
     * The shortest and the longest random wait of every call, in microseconds.
     */
    protected const int DELAY_MIN = 1_000;
    protected const int DELAY_MAX = 5_000;

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
            throw new RuntimeException('A blank string is no secret to hash.');
        }

        // bcrypt cannot hash past a NUL byte (password_hash() throws a ValueError); no browser sends one in a form
        if (str_contains($string, "\0")) {
            throw new InvalidArgumentException('bcrypt cannot hash a string with a NUL byte.');
        }

        // Randomly sleep some milliseconds
        $this->sleep();

        return password_hash($string, PASSWORD_BCRYPT, ['cost' => static::COST]);
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
     * Sleeps from DELAY_MIN to DELAY_MAX microseconds at random, so that the time of a call tells nothing of what it
     * compared.
     */
    protected function sleep(): void
    {
        usleep(random_int(static::DELAY_MIN, static::DELAY_MAX));
    }

    /**
     * The one place a string is verified against a hash (the expensive part).
     */
    protected function verify(string $string, string $hash): bool
    {
        return password_verify($string, $hash);
    }
}
