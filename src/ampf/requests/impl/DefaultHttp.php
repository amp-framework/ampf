<?php

declare(strict_types=1);

namespace ampf\requests\impl;

use ampf\beans\access\RouteResolverAccess;
use ampf\beans\access\XsrfTokenServiceAccess;
use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\requests\HttpRequest;
use RuntimeException;

/**
 * phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
class DefaultHttp implements BeanFactoryAccess, HttpRequest
{
    use DefaultBeanFactoryAccess;
    use RouteResolverAccess;
    use XsrfTokenServiceAccess;

    /** @var ?array<string, string|array> */
    protected ?array $get = null;

    /** @var ?array<string, string|array> */
    protected ?array $post = null;

    /** @var ?array<string, string|array> */
    protected ?array $cookie = null;

    /** @var ?array<string, string|array> */
    protected ?array $server = null;

    protected ?string $responseBody = null;

    /** @var ?array<string, string> */
    protected ?array $responseRedirect = null;

    protected int $responseStatusCode = 200;

    /** @var array<string, string> */
    protected array $headers = [];

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

    public function addHeader(string $key, string $value): self
    {
        if (trim($key) === '' || trim($value) === '') {
            throw new RuntimeException();
        }

        $this->headers[] = "{$key}: {$value}";

        return $this;
    }

    public function destroyCookieParam(string $key): self
    {
        if (!$this->hasCookieParam($key)) {
            return $this;
        }

        // Delete the cookie in the browser
        setcookie(
            $key,
            '',
            0,
            '/',
        );

        // And in our object
        unset($this->cookie[$key]);

        return $this;
    }

    public function hasCookieParam(string $key): bool
    {
        return isset($this->cookie[$key]);
    }

    public function flush(): self
    {
        http_response_code($this->responseStatusCode);

        foreach ($this->headers as $header) {
            header($header, true);
        }
        $this->headers = [];

        if ($this->responseRedirect !== null) {
            header('Location: ' . $this->responseRedirect['target'], true, $this->responseRedirect['code']);
            $this->responseRedirect = null;
        }

        if ($this->responseBody !== null) {
            echo $this->responseBody;
            $this->responseBody = null;
        }

        return $this;
    }

    /**
     * @return \stdClass[]
     */
    public function getAcceptedLanguages(): array
    {
        if (!$this->hasServerParam('HTTP_ACCEPT_LANGUAGE')) {
            return [];
        }

        $serverParam = explode(
            ',',
            $this->getServerParam('HTTP_ACCEPT_LANGUAGE'),
        );

        $results = [];
        foreach ($serverParam as $language) {
            $language = trim($language);
            $quality = ((float)1);
            if ($language === '') {
                continue;
            }

            if (str_contains($language, ';')) {
                [$language, $quality] = explode(';', $language);
                $language = trim($language);
                $quality = trim($quality);
                if ($language === '' || $quality === '' || !str_starts_with($quality, 'q=')) {
                    continue;
                }

                $quality = trim(substr($quality, strlen('q=')));
                if (((string)(float)$quality) !== $quality || $quality > 1 || $quality <= 0) {
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

    public function hasServerParam(string $key): bool
    {
        return isset($this->server[$key]);
    }

    public function getServerParam(string $key): mixed
    {
        if (!$this->hasServerParam($key)) {
            return null;
        }

        return $this->server[$key];
    }

    public function getController(): string
    {
        return $this->getRouteResolver()->getControllerByRoutePattern(
            $this->getRoute(),
        );
    }

    public function getCookieParam(string $key): mixed
    {
        if (!$this->hasCookieParam($key)) {
            return null;
        }

        return $this->cookie[$key];
    }

    public function getPostParam(string $key): mixed
    {
        if (!$this->hasPostParam($key)) {
            return null;
        }

        return $this->post[$key];
    }

    public function hasPostParam(string $key): bool
    {
        return isset($this->post[$key]);
    }

    public function getRefererLocalized(): ?string
    {
        // Get the raw referer
        $referer = $this->getRefererRaw();
        if (!$referer) {
            return null;
        }

        // Get the HTTP host, aka domain name of this project
        $domain = ('://' . $this->server['HTTP_HOST'] . '/');

        // Do we have $domain as a domain name in the referer?
        $i = mb_strpos($referer, $domain);
        if ($i !== false) {
            // Yes, so remove it from the referer
            $referer = mb_substr($referer, ($i + mb_strlen($domain)));
        }
        // Trim beginning slashes of the resulting referer...
        $referer = ltrim($referer, '/');

        // Get our app prefix, aka the webserver document root prefix of our app
        $prefix = ltrim($this->getDirname($this->server['SCRIPT_NAME']), '/');

        // Is $prefix non-empty, and do we have $prefix as a real prefix of our referer?
        $i = mb_strpos($referer, $prefix);
        if ($prefix !== '' && $i === 0) {
            // Yes, so remove it from the referer
            $referer = mb_substr($referer, ($i + mb_strlen($prefix)));
        }

        // Trim beginning slashes of the resulting referer...
        $referer = ltrim($referer, '/');

        // And return with the resulting referer, null if we are empty
        if (trim($referer) === '') {
            return null;
        }

        return $referer;
    }

    public function getRefererRaw(): ?string
    {
        $referer = $this->server['HTTP_REFERER'];
        if (!$referer || trim($referer) === '') {
            return null;
        }

        return $referer;
    }

    public function getResponse(): string
    {
        return $this->responseBody;
    }

    public function getRouteID(): string
    {
        return $this->getRouteResolver()->getRouteIDByRoutePattern(
            $this->getRoute(),
        );
    }

    /** @return string[] */
    public function getRouteParams(): array
    {
        return $this->getRouteResolver()->getParamsByRoutePattern(
            $this->getRoute(),
        );
    }

    public function hasCorrectToken(): bool
    {
        $tokenKey = $this->getXsrfTokenService()->getTokenIDForRequest();
        // no token in request - cannot have correct token
        if (!$this->hasGetParam($tokenKey)) {
            return false;
        }

        // take the token
        $tokenValue = $this->getGetParam($tokenKey);

        // and return whether it is correct
        return $this->getXsrfTokenService()->isCorrectToken($tokenValue);
    }

    public function hasGetParam(string $key): bool
    {
        return isset($this->get[$key]);
    }

    public function getGetParam(string $key): mixed
    {
        if (!$this->hasGetParam($key)) {
            return null;
        }

        return $this->get[$key];
    }

    public function isPostRequest(): bool
    {
        return
            $this->hasServerParam('REQUEST_METHOD')
            && $this->getServerParam('REQUEST_METHOD') === 'POST'
        ;
    }

    public function isRedirect(): bool
    {
        return $this->responseRedirect !== null;
    }

    public function setRedirect(
        string $routeID,
        ?array $params = null,
        ?string $code = null,
        ?bool $addToken = null,
        ?string $hashParam = null,
    ): self {
        if ($this->responseBody !== null) {
            throw new RuntimeException();
        }
        if ($params === null) {
            $params = [];
        }
        if (trim($code) === '') {
            $code = '301';
        }
        if ($addToken === null) {
            $addToken = false;
        }
        if ($hashParam === null) {
            $hashParam = '';
        }

        $this->responseRedirect = [
            'target' => $this->getActionLink($routeID, $params, $addToken, $hashParam),
            'code' => $code,
        ];

        return $this;
    }

    public function getActionLink(
        string $routeID,
        ?array $params = null,
        ?bool $addToken = false,
        ?string $hashParam = null,
    ): string {
        if ($params === null) {
            $params = [];
        }
        if ($hashParam === null) {
            $hashParam = '';
        }

        if ($addToken === true) {
            $tokenKey = $this->getXsrfTokenService()->getTokenIDForRequest();
            $tokenValue = $this->getXsrfTokenService()->getNewToken();
            $params[$tokenKey] = $tokenValue;
        }

        $routePattern = $this->getRouteResolver()->getRoutePatternByRouteID($routeID, $params);
        if ($routePattern === null) {
            throw new RuntimeException("Route pattern not found for routeID {$routeID}");
        }

        $notDefinedParams = $this->getRouteResolver()->getNotDefinedParams($routeID, $params);
        if (count($notDefinedParams) > 0) {
            $additionalParams = [];
            foreach ($notDefinedParams as $paramKey => $paramValue) {
                $additionalParams[] = (rawurlencode($paramKey) . '=' . rawurlencode($paramValue));
            }
            $routePattern .= ('?' . implode('&', $additionalParams));
        }

        if (trim($hashParam) !== '') {
            $routePattern .= ('#' . rawurlencode($hashParam));
        }

        return $this->getLink($routePattern);
    }

    public function getLink(string $relative): string
    {
        $path = $this->getDirname($this->server['SCRIPT_NAME']);

        $route = '';
        if (trim($path) !== '') {
            $route .= ('/' . $path);
        }
        $route .= ('/' . $relative);

        return $route;
    }

    public function setResponse(string $response): self
    {
        if ($this->responseRedirect !== null) {
            throw new RuntimeException();
        }

        $this->responseBody = $response;

        return $this;
    }

    public function setStatusCode(int $statusCode): self
    {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new RuntimeException();
        }

        $this->responseStatusCode = $statusCode;

        return $this;
    }

    protected function getRoute(): string
    {
        $route = $this->server['REQUEST_URI'];
        // Remove beginning slashes, just to be sure
        $route = ltrim($route, '/');

        // Get the base path
        $base = $this->getDirname($this->server['SCRIPT_NAME']);
        // If it is set, remove it from the route
        if ($base !== '' && str_starts_with($route, $base)) {
            $route = substr($route, mb_strlen($base));
        }

        // search for a questionmark and only take the string before it
        // this is done because we don't want to have GET-params into the route
        $questionMarkPosition = strpos($route, '?');
        if ($questionMarkPosition !== false) {
            $route = substr($route, 0, $questionMarkPosition);
        }

        // Remove beginning slashes again, just to be sure...
        // There still might be some when running directly on a domain and not in a subdirectory
        return ltrim($route, '/');
    }

    protected function getDirname(string $path): string
    {
        // replace backslashes with slashes (windows)
        $path = str_replace('\\', '/', $path);
        // remove trailing slashes
        $path = trim($path, '/');
        // explode for slashes
        $path = explode('/', $path);
        if (count($path) < 1) {
            throw new RuntimeException();
        }

        foreach ($path as $pathKey => $value) {
            // Remove empty paths information (this changes 'blub//didub' to 'blub/didub')
            if ($value === '') {
                unset($path[$pathKey]);

                continue;
            }

            // A-Z a-z _ . % -
            if (!preg_match('/^[A-Za-z0-9_\.%\-]+$/', $value)) {
                throw new RuntimeException();
            }
        }

        array_pop($path);

        if (count($path) === 0) {
            return '';
        }

        return implode('/', $path);
    }
}
