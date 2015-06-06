<?php

namespace ampf\services\xsrfToken;

interface XsrfTokenService
{
	public function getOldToken();

	public function getToken();

	public function getTokenIDForRequest();

	public function isCorrectToken($token);
}
