<?php

declare(strict_types=1);

namespace ampf\Testing;

/**
 * For a PHPUnit test case: PHPUnit's expectExceptionMessage() passes when the message only contains the text, which
 * lets a message lose its beginning or its end unnoticed. A message a person reads is pinned whole.
 */
trait ExpectsExactMessage
{
    /** The exception's message is this and nothing else. */
    protected function expectExceptionMessageExactly(string $message): void
    {
        $this->expectExceptionMessageMatches('/\A' . preg_quote($message, '/') . '\z/');
    }
}
