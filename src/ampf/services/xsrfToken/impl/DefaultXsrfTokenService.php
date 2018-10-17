<?php

namespace ampf\services\xsrfToken\impl;

use \ampf\beans\BeanFactoryAccess;
use ampf\services\xsrfToken\XsrfTokenService;

class DefaultXsrfTokenService implements BeanFactoryAccess, XsrfTokenService
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;
	use \ampf\beans\access\SessionServiceAccess;

	static protected $TOKEN_ID_REQUEST = 'stkn';
	static protected $TOKEN_ID_SESSION = '_xsrfToken';
	static protected $TOKEN_LEN = 6;
	static protected $TOKEN_QUEUE_COUNT = 15;

	/**
	 * @var \SplQueue|null
	 */
	protected $tokenQueue = null;

	/**
	 * @var string|null
	 */
	protected $currentToken = null;

	/**
	 * @return string
	 */
	public function getNewToken(): string
	{
		if (is_null($this->currentToken))
		{
			// Get the tokenQueue from the session
			$tokenQueue = $this->getTokenQueue();

			// Generate a new token
			$random             = random_bytes(static::$TOKEN_LEN);
			$random             = bin2hex($random);
			$this->currentToken = substr($random, 0, static::$TOKEN_LEN);

			// Store it into the queue
			$tokenQueue->enqueue($this->currentToken);
			// If our queue is full, remove the last one
			if ($tokenQueue->count() > static::$TOKEN_QUEUE_COUNT) {
				$tokenQueue->dequeue();
			}
			// And save back our tokenQueue into the session
			$this->setTokenQueue();
		}
		return $this->currentToken;
	}

	/**
	 * @return string
	 */
	public function getTokenIDForRequest()
	{
		return self::$TOKEN_ID_REQUEST;
	}

	/**
	 * @param string $token
	 * @return boolean
	 */
	public function isCorrectToken(string $token)
	{
		// Make sure the token has the correct format
		if (trim($token) == '') return false;
		if (strlen($token) != self::$TOKEN_LEN) return false;

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
	 * @return \SplQueue
	 */
	protected function getTokenQueue(): \SplQueue
	{
		if (is_null($this->tokenQueue))
		{
			$this->tokenQueue = new \SplQueue();

			if ($this->getSessionService()->hasAttribute(self::$TOKEN_ID_SESSION))
			{
				$tokenQueue = $this->getSessionService()->getAttribute(self::$TOKEN_ID_SESSION);
				if ($tokenQueue instanceof \SplQueue) $this->tokenQueue = $tokenQueue;
			}
		}
		return $this->tokenQueue;
	}

	/**
	 * Re-writes the tokenQueue into the session
	 *
	 * @return bool
	 */
	protected function setTokenQueue(): bool
	{
		$this->getSessionService()->setAttribute(
			self::$TOKEN_ID_SESSION,
			$this->tokenQueue
		);
		return true;
	}
}
