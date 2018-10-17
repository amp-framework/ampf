<?php

namespace ampf\services\xsrfToken;

interface XsrfTokenService
{
	/**
	 * @return string
	 */
	public function getNewToken();

	/**
	 * @return string
	 */
	public function getTokenIDForRequest();

	/**
	 * @param string $token
	 * @return boolean
	 */
	public function isCorrectToken(string $token);
}
