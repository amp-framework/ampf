<?php

declare(strict_types=1);

namespace ampf\Service\XsrfToken;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\Service\SessionServiceAccess;
use RuntimeException;
use SplQueue;

/**
 * One-time request tokens against cross-site request forgery: 128 random bits as 32 hex characters, kept in a
 * session-backed queue of the last TOKEN_QUEUE_COUNT issued ones. A token is accepted once.
 */
class XsrfTokenService implements BeanFactoryAccessInterface, XsrfTokenServiceInterface
{
    use BeanFactoryAccess;
    use SessionServiceAccess;

    protected const string TOKEN_ID_REQUEST = 'stkn';
    protected const string TOKEN_ID_SESSION = '_xsrfToken';

    /**
     * The random bytes of a token; its text is their hex form, twice as long (TOKEN_LEN).
     */
    protected const int TOKEN_BYTES = 16;
    protected const int TOKEN_LEN = 32;
    protected const int TOKEN_QUEUE_COUNT = 15;

    /**
     * @var ?SplQueue<string>
     */
    protected ?SplQueue $tokenQueue = null;

    protected ?string $currentToken = null;

    public function getNewToken(): string
    {
        if ($this->currentToken === null) {
            // Get the tokenQueue from the session
            $tokenQueue = $this->getTokenQueue();

            // Generate a new token: every hex character of it is random
            // @phpstan-ignore argument.type
            $this->currentToken = bin2hex(random_bytes(static::TOKEN_BYTES));

            // Store it into the queue
            $tokenQueue->enqueue($this->currentToken);

            // If our queue is full, remove the last one
            if ($tokenQueue->count() > static::TOKEN_QUEUE_COUNT) {
                $tokenQueue->dequeue();
            }

            // And save back our tokenQueue into the session
            $this->setTokenQueue();
        }

        if ($this->currentToken === null) {
            throw new RuntimeException();
        }

        return $this->currentToken;
    }

    public function getTokenIDForRequest(): string
    {
        return static::TOKEN_ID_REQUEST;
    }

    public function isCorrectToken(string $token): bool
    {
        // Make sure the token has the correct format: anything else was never issued (a token issued before
        // tokens had their current length included)
        if (strlen($token) !== static::TOKEN_LEN || !ctype_xdigit($token)) {
            return false;
        }

        // Get our tokenQueue
        $tokenQueue = $this->getTokenQueue();

        // Iterate over the values
        foreach ($tokenQueue as $i => $realToken) {
            // If we found the token (the known string first, the given one second: hash_equals()'s order)
            if (hash_equals($realToken, $token)) {
                // Remove it from our tokenQueue
                $tokenQueue->offsetUnset($i);
                // And save back the queue to the session
                $this->setTokenQueue();

                return true;
            }
        }

        return false;
    }

    /**
     * Gets the \SplQueue in which the tokens are being saved
     *
     * @return SplQueue<string>
     */
    protected function getTokenQueue(): SplQueue
    {
        if ($this->tokenQueue === null) {
            $this->tokenQueue = new SplQueue();

            if ($this->getSessionService()->hasAttribute(static::TOKEN_ID_SESSION)) {
                $tokenQueue = $this->getSessionService()->getAttribute(static::TOKEN_ID_SESSION);

                if ($tokenQueue instanceof SplQueue) {
                    $this->tokenQueue = $tokenQueue;
                }
            }
        }

        return $this->tokenQueue;
    }

    /**
     * Re-writes the tokenQueue into the session
     */
    protected function setTokenQueue(): void
    {
        // @phpcs:ignore SlevomatCodingStandard.Functions.RequireSingleLineCall.RequiredSingleLineCall
        $this->getSessionService()->setAttribute(
            static::TOKEN_ID_SESSION,
            $this->tokenQueue,
        );
    }
}
