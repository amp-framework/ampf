<?php

namespace ampf\requests\impl;

use ampf\requests\HttpRequest;

class DefaultHttp implements HttpRequest
{
	use \ampf\beans\access\BeanFactoryAccess;
	use \ampf\beans\access\RouteResolverAccess;
	use \ampf\beans\access\XsrfTokenServiceAccess;

	protected $get = null;
	protected $post = null;
	protected $cookie = null;
	protected $server = null;

	protected $responseBody = null;
	protected $responseRedirect = null;
	protected $responseStatusCode = 200;
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

	public function getRouteID()
	{
		return $this->getRouteResolver()->getRouteIDByRoutePattern(
			$this->getRoute()
		);
	}

	public function getController()
	{
		return $this->getRouteResolver()->getControllerByRoutePattern(
			$this->getRoute()
		);
	}

	public function getRouteParams()
	{
		return $this->getRouteResolver()->getParamsByRoutePattern(
			$this->getRoute()
		);
	}

	public function getLink($relative)
	{
		$path = $this->getDirname($this->server['PHP_SELF']);

		$route = '';
		if (!empty($path)) $route .= ('/' . $path);
		$route .= ('/' . $relative);

		return $route;
	}

	public function getActionLink($routeID, $params = null, $addToken = false)
	{
		if (is_null($params)) $params = array();

		if ($addToken === true)
		{
			$tokenKey = $this->getXsrfTokenService()->getTokenIDForRequest();
			$tokenValue = $this->getXsrfTokenService()->getToken();
			$params[$tokenKey] = $tokenValue;
		}

		$routePattern = $this->getRouteResolver()->getRoutePatternByRouteID($routeID, $params);

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

		$routePattern = $this->getLink($routePattern);
		return $routePattern;
	}

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

	public function setRedirect($routeID, $params = null, $code = null)
	{
		if ($this->responseBody !== null) throw new \Exception();
		if ($params === null || !is_array($params)) $params = array();
		if ($code === null || trim($code) === '') $code = '301';

		$this->responseRedirect = array(
			'target' => $this->getActionLink($routeID, $params),
			'code' => ((string)$code),
		);
	}

	public function setResponse($response)
	{
		if (!is_null($this->responseRedirect)) throw new \Exception();

		$this->responseBody = $response;
	}

	public function setStatusCode($statusCode)
	{
		if (!is_scalar($statusCode)) throw new \Exception();
		$statusCode = ((int)$statusCode);
		if ($statusCode < 100 || $statusCode > 599) throw new \Exception();
		$this->responseStatusCode = $statusCode;
	}

	public function addHeader($key, $value)
	{
		if (!is_scalar($key)) throw new \Exception();
		if (!is_scalar($value)) throw new \Exception();
		$key = ((string)$key);
		$value = ((string)$value);
		if (trim($key) == '') throw new \Exception();
		if (trim($value) == '') throw new \Exception();

		$this->headers[] = "{$key}: {$value}";
	}


	public function getResponse()
	{
		return $this->responseBody;
	}

	public function isRedirect()
	{
		return (!is_null($this->responseRedirect));
	}

	public function flush()
	{
		http_response_code($this->responseStatusCode);

		foreach ($this->headers as $header)
		{
			header($header);
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
	}

	public function isPostRequest()
	{
		return (
			$this->hasServerParam('REQUEST_METHOD')
			&& $this->getServerParam('REQUEST_METHOD') == 'POST'
		);
	}

	public function hasGetParam($key)
	{
		return isset($this->get[$key]);
	}

	public function hasPostParam($key)
	{
		return isset($this->post[$key]);
	}

	public function hasCookieParam($key)
	{
		return isset($this->cookie[$key]);
	}

	public function hasServerParam($key)
	{
		return isset($this->server[$key]);
	}

	public function getGetParam($key)
	{
		if (!$this->hasGetParam($key)) return null;
		return $this->get[$key];
	}

	public function getPostParam($key)
	{
		if (!$this->hasPostParam($key)) return null;
		return $this->post[$key];
	}

	public function getCookieParam($key)
	{
		if (!$this->hasCookieParam($key)) return null;
		return $this->cookie[$key];
	}

	public function getServerParam($key)
	{
		if (!$this->hasServerParam($key)) return null;
		return $this->server[$key];
	}

	/**
	 * Protected methods
	 */

	protected function getRoute()
	{
		$route = $this->server['REQUEST_URI'];
		// Remove beginning slashes, just to be sure
		$route = ltrim($route, '/');

		// Get the base path
		$base = $this->getDirname($this->server['PHP_SELF']);
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

	protected function getDirname($path)
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
