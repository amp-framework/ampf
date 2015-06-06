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

	protected $oldToken = null;
	protected $tokenCache = null;

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

	public function getToken()
	{
		if (is_null($this->tokenCache))
		{
			// do this to make sure we do not override the old token in the session
			$this->getOldToken();

			// generate a new random token
			$random = mcrypt_create_iv(256, \MCRYPT_DEV_URANDOM);
			$random = base64_encode($random);
			$random = str_replace('+', '-', $random);
			$random = str_replace('/', '_', $random);
			$random = str_replace('=', '', $random);
			$this->tokenCache = substr($random, 0, self::$TOKEN_LEN);

			// and store it in the session
			$this->getSessionService()->setAttribute(
				self::$TOKEN_ID_SESSION,
				$this->tokenCache
			);
		}
		return $this->tokenCache;
	}

	public function getTokenIDForRequest()
	{
		return self::$TOKEN_ID_REQUEST;
	}

	public function isCorrectToken($token)
	{
		if (trim($token) == '') return false;
		if (strlen($token) != self::$TOKEN_LEN) return false;

		return ($token === $this->getOldToken());
	}
}
