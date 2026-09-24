<?php

declare(strict_types=1);

namespace ampf\Service\Hasher;

use InvalidArgumentException;
use RuntimeException;

/** Password hashing: bcrypt hashes, checked in constant time, with the time of a check even where there is none. */
interface HasherServiceInterface
{
    /**
     * Spends the time of one check() against a dummy hash, so that a lookup that found nothing to check against
     * answers as slowly as one that did.
     */
    public function avoidTimingAttack(string $input): void;

    /**
     * Whether the string matches the stored hash. A blank string, and a stored value that is no bcrypt hash (a
     * value set by hand, a hash of another algorithm), answer false after a dummy check of the same duration: the
     * caller cannot tell them from a wrong string, and nothing throws.
     */
    public function check(string $string, string $storedHash): bool;

    /**
     * A bcrypt hash of the string (60 characters).
     *
     * @throws InvalidArgumentException for a string with a NUL byte, which bcrypt cannot hash
     * @throws RuntimeException for a blank string
     */
    public function hash(string $string): string;

    /**
     * Whether a stored hash differs from what hash() makes today (another algorithm, a lower cost): after a
     * successful check() the caller stores a new hash() of the same string.
     */
    public function needsRehash(string $storedHash): bool;
}
