<?php

namespace ampf\services\session\impl;

use ampf\services\session\SessionService;

class DefaultSessionService implements SessionService
{
	public function __construct()
	{
		if (session_start() === false)
		{
			throw new \Exception("Failed to start session.");
		}
	}

	/**
	 * @return void
	 */
	public function destroy()
	{
		$_SESSION = array();
		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				(time() - 42000),
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}
		session_destroy();
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getAttribute($key)
	{
		if (!$this->hasAttribute($key)) return null;
		return $_SESSION[$key];
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasAttribute($key)
	{
		return isset($_SESSION[$key]);
	}

	/**
	 * @param string $key
	 * @return void
	 */
	public function removeAttribute($key)
	{
		if (!$this->hasAttribute($key)) return;
		// dereference possible objects
		$_SESSION[$key] = null;
		// and unset it completely
		unset($_SESSION[$key]);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 * @throws \Exception
	 */
	public function setAttribute($key, $value)
	{
		if (!is_string($key) || trim($key) == '') throw new \Exception();
		$_SESSION[$key] = $value;
	}
}
