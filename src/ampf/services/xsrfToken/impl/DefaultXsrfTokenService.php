<?php

namespace ampf\services\xsrfToken\impl;

use ampf\services\xsrfToken\XsrfTokenService;

class DefaultXsrfTokenService implements XsrfTokenService
{
	use \ampf\beans\access\BeanFactoryAccess;
	use \ampf\beans\access\SessionServiceAccess;

	static protected $TOKEN_ID_REQUEST = 'stkn';
	static protected $TOKEN_ID_SESSION = '_xsrfToken';
	static protected $TOKEN_LEN = 6;

	/**
	 * @var string
	 */
	protected $oldToken = null;
	/**
	 * @var string
	 */
	protected $tokenCache = null;

	/**
	 * @return string
	 */
	public function getOldToken()
	{
		if (is_null($this->oldToken))
		{
			if ($this->getSessionService()->hasAttribute(self::$TOKEN_ID_SESSION))
			{
				// store the token from the session into our object
				$this->oldToken = $this->getSessionService()->getAttribute(
					self::$TOKEN_ID_SESSION
				);
				// and remove the token from the session
				$this->getSessionService()->removeAttribute(self::$TOKEN_ID_SESSION);
			}
		}
		return $this->oldToken;
	}

	/**
	 * @return string
	 */
	public function getToken()
	{
		if (is_null($this->tokenCache))
		{
			// do this to make sure we do not override the old token in the session
			$this->getOldToken();

			$random = mcrypt_create_iv(static::$TOKEN_LEN);
			$random = bin2hex($random);
			$this->tokenCache = substr($random, 0, static::$TOKEN_LEN);

			// and store it in the session
			$this->getSessionService()->setAttribute(
				self::$TOKEN_ID_SESSION,
				$this->tokenCache
			);
		}
		return $this->tokenCache;
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
	public function isCorrectToken($token)
	{
		if (trim($token) == '') return false;
		if (strlen($token) != self::$TOKEN_LEN) return false;

		$oldToken = $this->getOldToken();
		if (trim($oldToken) == '') return false;
		if (strlen($oldToken) != self::$TOKEN_LEN) return false;

		return hash_equals($this->getOldToken(), $token);
	}
}
