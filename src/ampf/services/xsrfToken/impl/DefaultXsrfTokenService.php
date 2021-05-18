<?php

declare(strict_types=1);

namespace ampf\services\xsrfToken\impl;

use ampf\beans\access\SessionServiceAccess;
use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\services\xsrfToken\XsrfTokenService;
use RuntimeException;
use SplQueue;

class DefaultXsrfTokenService implements BeanFactoryAccess, XsrfTokenService
{
    use DefaultBeanFactoryAccess;
    use SessionServiceAccess;

    protected const TOKEN_ID_REQUEST = 'stkn';
    protected const TOKEN_ID_SESSION = '_xsrfToken';
    protected const TOKEN_LEN = 6;
    protected const TOKEN_QUEUE_COUNT = 15;

    /** @var ?\SplQueue<string> */
    protected ?SplQueue $tokenQueue = null;

    protected ?string $currentToken = null;

    public function getNewToken(): string
    {
        if ($this->currentToken === null) {
            // Get the tokenQueue from the session
            $tokenQueue = $this->getTokenQueue();

            // Generate a new token
            $random = random_bytes(static::TOKEN_LEN);
            $random = bin2hex($random);
            $this->currentToken = mb_substr($random, 0, static::TOKEN_LEN);

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
        // Make sure the token has the correct format
        if (trim($token) === '') {
            return false;
        }

        if (mb_strlen($token) !== static::TOKEN_LEN) {
            return false;
        }

        // Get our tokenQueue
        $tokenQueue = $this->getTokenQueue();
        // Iterate over the values
        foreach ($tokenQueue as $i => $realToken) {
            // If we found the token
            if (hash_equals($token, $realToken)) {
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
     * @return \SplQueue<string>
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
        $this->getSessionService()->setAttribute(
            static::TOKEN_ID_SESSION,
            $this->tokenQueue,
        );
    }
}
