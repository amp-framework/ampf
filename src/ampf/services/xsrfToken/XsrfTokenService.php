<?php

namespace ampf\services\xsrfToken;

interface XsrfTokenService
{
	/**
	 * @return string
	 */
	public function getOldToken();

	/**
	 * @return string
	 */
	public function getToken();

	/**
	 * @return string
	 */
	public function getTokenIDForRequest();

	/**
	 * @param string $token
	 * @return boolean
	 */
	public function isCorrectToken($token);
}
