<?php

namespace ampf\requests;

interface HttpRequest
{
	public function addHeader($key, $value);

	public function flush();

	/**
	 * @return \stdClass[]
	 */
	public function getAcceptedLanguages();

	public function getActionLink($routeID, $params = null, $addToken = false);

	public function getController();

	public function getCookieParam($key);

	public function getGetParam($key);

	public function getLink($relative);

	public function getPostParam($key);

	public function getResponse();

	public function getRouteID();

	public function getServerParam($key);

	public function hasCookieParam($key);

	public function hasCorrectToken();

	public function hasGetParam($key);

	public function hasPostParam($key);

	public function hasServerParam($key);

	public function isPostRequest();

	public function isRedirect();

	public function setRedirect($routeID, $params = null, $code = null, $addToken = null);

	public function setStatusCode($statusCode);

	public function setResponse($response);
}
