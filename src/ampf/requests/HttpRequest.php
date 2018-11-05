<?php

namespace ampf\requests;

interface HttpRequest
{
	/**
	 * @param string $key
	 * @param string $value
	 * @return HttpRequest
	 * @throws \Exception
	 */
	public function addHeader(string $key, string $value);

	/**
	 * @param string $key
	 * @return HttpRequest
	 */
	public function destroyCookieParam(string $key);

	/**
	 * @return HttpRequest
	 */
	public function flush();

	/**
	 * @return \stdClass[]
	 */
	public function getAcceptedLanguages();

	/**
	 * @param string $routeID
	 * @param array $params
	 * @param bool $addToken
	 * @param string $hashParam
	 * @return string
	 */
	public function getActionLink(
		string $routeID,
		array $params = null,
		bool $addToken = false,
		string $hashParam = null
	);

	/**
	 * @return string
	 */
	public function getController();

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getCookieParam(string $key);

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getGetParam(string $key);

	/**
	 * @param string $relative
	 * @return string
	 */
	public function getLink(string $relative);

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getPostParam(string $key);

	/**
	 * Returns the HTTP referer of this request (if any) localized, which here means
	 * cleared by the HTTP host and subdirectory.
	 *
	 * @return string|null
	 */
	public function getRefererLocalized(): ?string;

	/**
	 * Returns the HTTP referer of this request (if any).
	 *
	 * @return string|null
	 */
	public function getRefererRaw(): ?string;

	/**
	 * @return string
	 */
	public function getResponse();

	/**
	 * @return string
	 */
	public function getRouteID();

	/**
	 * @return array
	 */
	public function getRouteParams();

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getServerParam(string $key);

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasCookieParam(string $key);

	/**
	 * @return boolean
	 */
	public function hasCorrectToken();

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasGetParam(string $key);

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasPostParam(string $key);

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasServerParam(string $key);

	/**
	 * @return boolean
	 */
	public function isPostRequest();

	/**
	 * @return boolean
	 */
	public function isRedirect();

	/**
	 * @param string $routeID
	 * @param array $params
	 * @param string $code
	 * @param bool $addToken
	 * @param string $hashParam
	 * @return HttpRequest
	 * @throws \Exception
	 */
	public function setRedirect(
		string $routeID,
		array $params = null,
		string $code = null,
		bool $addToken = null,
		string $hashParam = null
	);

	/**
	 * @param string $response
	 * @return HttpRequest
	 * @throws \Exception
	 */
	public function setResponse(string $response);

	/**
	 * @param string $statusCode
	 * @return HttpRequest
	 * @throws \Exception
	 */
	public function setStatusCode(string $statusCode);
}
