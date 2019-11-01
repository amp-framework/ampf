<?php

namespace ampf\requests\impl;

use \ampf\beans\BeanFactoryAccess;
use \ampf\requests\HttpRequest;

class DefaultHttp implements BeanFactoryAccess, HttpRequest
{
	use \ampf\beans\impl\DefaultBeanFactoryAccess;
	use \ampf\beans\access\RouteResolverAccess;
	use \ampf\beans\access\XsrfTokenServiceAccess;

	/**
	 * @var array
	 */
	protected $get = null;

	/**
	 * @var array
	 */
	protected $post = null;

	/**
	 * @var array
	 */
	protected $cookie = null;

	/**
	 * @var array
	 */
	protected $server = null;

	/**
	 * @var string
	 */
	protected $responseBody = null;

	/**
	 * @var array
	 */
	protected $responseRedirect = null;

	/**
	 * @var string
	 */
	protected $responseStatusCode = '200';

	/**
	 * @var array
	 */
	protected $headers = array();

	public function __construct()
	{
		$this->get = $_GET;
		$this->post = $_POST;
		$this->cookie = $_COOKIE;
		$this->server = $_SERVER;

		// Set some default headers regarding browser caching
		$this->addHeader('Content-Type', 'text/html; charset=UTF-8');
		$this->addHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
		$this->addHeader('Pragma', 'no-cache');
		$this->addHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', 0));
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return HttpRequest
	 * @throws \Exception
	 */
	public function addHeader(string $key, string $value)
	{
		if (trim($key) == '') throw new \Exception();
		if (trim($value) == '') throw new \Exception();
		$this->headers[] = "{$key}: {$value}";
		return $this;
	}

	/**
	 * @param string $key
	 * @return HttpRequest
	 */
	public function destroyCookieParam(string $key)
	{
		if (!$this->hasCookieParam($key))
		{
			return;
		}
		// Delete the cookie in the browser
		setcookie(
			$key,
			'',
			0,
			'/'
		);
		// And in our object
		unset($this->cookie[$key]);
		return $this;
	}

	/**
	 * @return HttpRequest
	 */
	public function flush()
	{
		http_response_code($this->responseStatusCode);

		foreach ($this->headers as $header)
		{
			header($header, true);
		}
		$this->headers = array();

		if (!is_null($this->responseRedirect))
		{
			header('Location: ' . $this->responseRedirect['target'], true, $this->responseRedirect['code']);
			$this->responseRedirect = null;
		}

		if (!is_null($this->responseBody))
		{
			echo $this->responseBody;
			$this->responseBody = null;
		}

		return $this;
	}

	/**
	 * @return \stdClass[]
	 */
	public function getAcceptedLanguages()
	{
		if (!$this->hasServerParam('HTTP_ACCEPT_LANGUAGE'))
		{
			return array();
		}
		$serverParam = explode(
			',',
			$this->getServerParam('HTTP_ACCEPT_LANGUAGE')
		);

		$results = array();
		foreach ($serverParam as $language)
		{
			$language = trim($language);
			$quality = ((float)1);
			if ($language == '')
			{
				continue;
			}
			if (strpos($language, ';') !== false)
			{
				list($language, $quality) = explode(';', $language);
				$language = trim($language);
				$quality = trim($quality);
				if ($language == '' || $quality == '' || strpos($quality, 'q=') !== 0)
				{
					continue;
				}
				$quality = trim(substr($quality, strlen('q=')));
				if (((float)$quality) != $quality || $quality > 1 || $quality <= 0)
				{
					continue;
				}
				$quality = ((float)$quality);
			}

			$cresult = new \stdClass();
			$cresult->language = $language;
			$cresult->quality = $quality;

			$results[] = $cresult;
		}

		return $results;
	}

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
	)
	{
		if ($params === null) $params = array();
		if ($hashParam === null) $hashParam = '';

		if ($addToken === true)
		{
			$tokenKey = $this->getXsrfTokenService()->getTokenIDForRequest();
			$tokenValue = $this->getXsrfTokenService()->getNewToken();
			$params[$tokenKey] = $tokenValue;
		}

		$routePattern = $this->getRouteResolver()->getRoutePatternByRouteID($routeID, $params);
		if ($routePattern === null)
		{
			throw new \Exception("Route pattern not found for routeID {$routeID}");
		}

		$notDefinedParams = $this->getRouteResolver()->getNotDefinedParams($routeID, $params);
		if (count($notDefinedParams) > 0)
		{
			$additionalParams = array();
			foreach ($notDefinedParams as $paramKey => $paramValue)
			{
				$additionalParams[] = (rawurlencode($paramKey) . '=' . rawurlencode($paramValue));
			}
			$routePattern .= ('?' . implode('&', $additionalParams));
		}

		if (trim($hashParam) != '')
		{
			$routePattern .= ('#' . rawurlencode($hashParam));
		}

		$routePattern = $this->getLink($routePattern);
		return $routePattern;
	}

	/**
	 * @return string
	 */
	public function getController()
	{
		return $this->getRouteResolver()->getControllerByRoutePattern(
			$this->getRoute()
		);
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getCookieParam(string $key)
	{
		if (!$this->hasCookieParam($key)) return null;
		return $this->cookie[$key];
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getGetParam(string $key)
	{
		if (!$this->hasGetParam($key)) return null;
		return $this->get[$key];
	}

	/**
	 * @param string $relative
	 * @return string
	 */
	public function getLink(string $relative)
	{
		$path = $this->getDirname($this->server['SCRIPT_NAME']);

		$route = '';
		if (!empty($path)) $route .= ('/' . $path);
		$route .= ('/' . $relative);

		return $route;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getPostParam(string $key)
	{
		if (!$this->hasPostParam($key)) return null;
		return $this->post[$key];
	}

	/**
	 * @return null|string
	 */
	public function getRefererLocalized(): ?string
	{
		// Get the raw referer
		$referer = $this->getRefererRaw();
		if (!$referer) return null;

		// Get the HTTP host, aka domain name of this project
		$domain = ('://' . $this->server['HTTP_HOST'] . '/');
		// Do we have $domain as a domain name in the referer?
		if (($i = mb_strpos($referer, $domain)) !== false) {
			// Yes, so remove it from the referer
			$referer = mb_substr($referer, ($i + mb_strlen($domain)));
		}
		// Trim beginning slashes of the resulting referer...
		$referer = ltrim($referer, '/');

		// Get our app prefix, aka the webserver document root prefix of our app
		$prefix = ltrim($this->getDirname($this->server['SCRIPT_NAME']), '/');
		// Is $prefix non-empty, and do we have $prefix as a real prefix of our referer?
		if ($prefix != '' && ($i = mb_strpos($referer, $prefix)) === 0) {
			// Yes, so remove it from the referer
			$referer = mb_substr($referer, ($i + mb_strlen($prefix)));
		}
		// Trim beginning slashes of the resulting referer...
		$referer = ltrim($referer, '/');

		// And return with the resulting referer, null if we are empty
		if (trim($referer) == '') {
			return null;
		}
		return $referer;
	}

	/**
	 * @return null|string
	 */
	public function getRefererRaw(): ?string
	{
		$referer = $this->server['HTTP_REFERER'];
		if (!$referer || trim($referer) == '') return null;
		return $referer;
	}

	/**
	 * @return string
	 */
	public function getResponse()
	{
		return $this->responseBody;
	}

	/**
	 * @return string
	 */
	public function getRouteID()
	{
		return $this->getRouteResolver()->getRouteIDByRoutePattern(
			$this->getRoute()
		);
	}

	/**
	 * @return array
	 */
	public function getRouteParams()
	{
		return $this->getRouteResolver()->getParamsByRoutePattern(
			$this->getRoute()
		);
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getServerParam(string $key)
	{
		if (!$this->hasServerParam($key)) return null;
		return $this->server[$key];
	}

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasCookieParam(string $key)
	{
		return isset($this->cookie[$key]);
	}

	/**
	 * @return boolean
	 */
	public function hasCorrectToken()
	{
		$tokenKey = $this->getXsrfTokenService()->getTokenIDForRequest();
		// no token in request - cannot have correct token
		if (!$this->hasGetParam($tokenKey))
		{
			return false;
		}
		// take the token
		$tokenValue = $this->getGetParam($tokenKey);
		// and return whether it is correct
		return $this->getXsrfTokenService()->isCorrectToken($tokenValue);
	}

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasGetParam(string $key)
	{
		return isset($this->get[$key]);
	}

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasPostParam(string $key)
	{
		return isset($this->post[$key]);
	}

	/**
	 * @param string $key
	 * @return boolean
	 */
	public function hasServerParam(string $key)
	{
		return isset($this->server[$key]);
	}

	/**
	 * @return boolean
	 */
	public function isPostRequest()
	{
		return (
			$this->hasServerParam('REQUEST_METHOD')
			&& $this->getServerParam('REQUEST_METHOD') == 'POST'
		);
	}

	/**
	 * @return boolean
	 */
	public function isRedirect()
	{
		return (!is_null($this->responseRedirect));
	}

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
	)
	{
		if ($this->responseBody !== null) throw new \Exception();
		if ($params === null || !is_array($params)) $params = array();
		if ($code === null || trim($code) === '') $code = '301';
		if ($addToken === null) $addToken = false;
		if ($hashParam === null) $hashParam = '';

		$this->responseRedirect = array(
			'target' => $this->getActionLink($routeID, $params, $addToken, $hashParam),
			'code' => $code,
		);
		return $this;
	}

	/**
	 * @param string $response
	 * @return HttpRequest
	 * @throws \Exception
	 */
	public function setResponse(string $response)
	{
		if (!is_null($this->responseRedirect)) throw new \Exception();
		$this->responseBody = $response;
		return $this;
	}

	/**
	 * @param string $statusCode
	 * @return HttpRequest
	 * @throws \Exception
	 */
	public function setStatusCode(string $statusCode)
	{
		$statusCode = ((int)$statusCode);
		if ($statusCode < 100 || $statusCode > 599) throw new \Exception();
		$this->responseStatusCode = ((string)$statusCode);
		return $this;
	}

	/**
	 * Protected methods
	 */

	/**
	 * @return string
	 */
	protected function getRoute()
	{
		$route = $this->server['REQUEST_URI'];
		// Remove beginning slashes, just to be sure
		$route = ltrim($route, '/');

		// Get the base path
		$base = $this->getDirname($this->server['SCRIPT_NAME']);
		// If it is set, remove it from the route
		if ($base != '' && strpos($route, $base) === 0)
		{
			$route = substr($route, mb_strlen($base));
		}

		// search for a questionmark and only take the string before it
		// this is done because we don't want to have GET-params into the route
		$questionMarkPosition = strpos($route, '?');
		if ($questionMarkPosition !== false)
		{
			$route = substr($route, 0, $questionMarkPosition);
		}

		// Remove beginning slashes again, just to be sure...
		// There still might be some when running directly on a domain and not in a subdirectory
		$route = ltrim($route, '/');

		return $route;
	}

	/**
	 * @param string $path
	 * @return string
	 * @throws \Exception
	 */
	protected function getDirname(string $path)
	{
		// replace backslashes with slashes (windows)
		$path = str_replace('\\', '/', $path);
		// remove trailing slashes
		$path = trim($path, '/');
		// explode for slashes
		$path = explode('/', $path);
		if (!is_array($path) || count($path) < 1) throw new \Exception();
		foreach ($path as $pathKey => $value)
		{
			// Remove empty paths information (this changes 'blub//didub' to 'blub/didub')
			if ($value === '')
			{
				unset($path[$pathKey]);
				continue;
			}
			// A-Z a-z _ . % -
			if (!preg_match('/^[A-Za-z0-9_\.%\-]+$/', $value)) throw new \Exception();
		}
		array_pop($path);
		if (count($path) == 0) return '';
		return implode('/', $path);
	}
}
