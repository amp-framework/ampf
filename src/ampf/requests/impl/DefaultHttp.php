<?php

declare(strict_types=1);

namespace ampf\requests\impl;

use ampf\beans\access\RouteResolverAccess;
use ampf\beans\access\XsrfTokenServiceAccess;
use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\Functions;
use ampf\requests\HttpRequest;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
class DefaultHttp implements BeanFactoryAccess, HttpRequest
{
    use DefaultBeanFactoryAccess;
    use RouteResolverAccess;
    use XsrfTokenServiceAccess;

    /**
     * @var ?array<string, string|array<mixed, mixed>>
     */
    protected ?array $get = null;

    /**
     * @var ?array<string, string|array<mixed, mixed>>
     */
    protected ?array $post = null;

    /**
     * @var ?array<string, string|array<mixed, mixed>>
     */
    protected ?array $cookie = null;

    /**
     * @var ?array<string, string|array<mixed, mixed>>
     */
    protected ?array $server = null;

    protected ?string $responseBody = null;

    /**
     * @var ?array{code: int, target: string}
     */
    protected ?array $responseRedirect = null;

    protected int $responseStatusCode = 200;

    /**
     * @var array<int, string>
     */
    protected array $headers = [];

    /** Whether the text holds a C0 control character or DEL, a horizontal tab excepted. */
    protected static function hasControlCharacter(string $text): bool
    {
        return preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $text) === 1;
    }

    public function __construct()
    {
        $this->get = Functions::cleanGPCSLists($_GET);
        $this->post = Functions::cleanGPCSLists($_POST);
        $this->cookie = Functions::cleanGPCSLists($_COOKIE);
        $this->server = Functions::cleanGPCSLists($_SERVER);

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

        // A header's name is a token, and its value one line: nothing a client sent reaches header() otherwise
        if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $key) !== 1) {
            throw new RuntimeException('A header name must be a token.');
        }

        if (static::hasControlCharacter($value)) {
            throw new RuntimeException('A header value must not contain a control character.');
        }

        $this->headers[] = "{$key}: {$value}";

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function destroyCookieParam(string $key, array $options = []): self
    {
        if (!$this->hasCookieParam($key)) {
            return $this;
        }

        // Delete the cookie in the browser: an empty value, expired, with the attributes it was set with
        $this->sendCookie($key, '', ['expires' => 0] + $this->getCookieOptions($options));

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
        // Everything is checked before the first byte goes out: a refused header ends the request as an error
        foreach ($this->headers as $header) {
            if (static::hasControlCharacter($header)) {
                throw new RuntimeException('A header must not contain a control character.');
            }
        }

        if ($this->responseRedirect !== null && static::hasControlCharacter($this->responseRedirect['target'])) {
            throw new RuntimeException('A redirect target must not contain a control character.');
        }

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
     * @return list<\stdClass>
     */
    public function getAcceptedLanguages(): array
    {
        if (!$this->hasServerParam('HTTP_ACCEPT_LANGUAGE')) {
            return [];
        }

        $httpAcceptLanguage = $this->getServerParam('HTTP_ACCEPT_LANGUAGE');

        if (!is_string($httpAcceptLanguage)) {
            $httpAcceptLanguage = '';
        }
        $serverParam = explode(',', $httpAcceptLanguage);

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

            $cresult = new stdClass();
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
        assert(isset($this->server[$key]));

        return $this->server[$key];
    }

    public function getController(): ?string
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
        assert(isset($this->cookie[$key]));

        return $this->cookie[$key];
    }

    public function getPostParam(string $key): mixed
    {
        if (!$this->hasPostParam($key)) {
            return null;
        }
        assert(isset($this->post[$key]));

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

        if ($referer === null) {
            return null;
        }

        // Ours only when its origin is this request's host: a path of another site means nothing here
        $path = $this->getRefererPathOnThisHost($referer);

        if ($path === null) {
            return null;
        }

        // Trim beginning slashes of the resulting referer...
        $referer = ltrim($path, '/');

        // Get our app prefix, aka the webserver document root prefix of our app
        $scriptName = $this->getServerParam('SCRIPT_NAME');

        if (!is_string($scriptName)) {
            throw new RuntimeException();
        }
        $prefix = ltrim($this->getDirname($scriptName), '/');

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
        $referer = $this->getServerParam('HTTP_REFERER');

        if (!is_string($referer) || trim($referer) === '') {
            return null;
        }

        return $referer;
    }

    public function getResponse(): string
    {
        if ($this->responseBody === null) {
            return '';
        }

        return $this->responseBody;
    }

    public function getRouteID(): ?string
    {
        return $this->getRouteResolver()->getRouteIDByRoutePattern(
            $this->getRoute(),
        );
    }

    /**
     * @return ?array<string, string>
     */
    public function getRouteParams(): ?array
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

        if (!is_string($tokenValue)) {
            $tokenValue = '';
        }

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
        assert(isset($this->get[$key]));

        return $this->get[$key];
    }

    public function isPostRequest(): bool
    {
        return
            $this->hasServerParam('REQUEST_METHOD')
            && $this->getServerParam('REQUEST_METHOD') === 'POST';
    }

    public function isRedirect(): bool
    {
        return $this->responseRedirect !== null;
    }

    /**
     * @param array<string, string> $params
     */
    public function setRedirect(
        string $routeID,
        ?array $params = null,
        ?int $code = null,
        ?bool $addToken = null,
        ?string $hashParam = null,
    ): self {
        if ($this->responseBody !== null) {
            throw new RuntimeException();
        }

        if ($params === null) {
            $params = [];
        }

        if ($code === null) {
            $code = 301;
        }

        if ($addToken === null) {
            $addToken = false;
        }

        if ($hashParam === null) {
            $hashParam = '';
        }

        $target = $this->getActionLink($routeID, $params, $addToken, $hashParam);

        if (static::hasControlCharacter($target)) {
            throw new RuntimeException('A redirect target must not contain a control character.');
        }

        $this->responseRedirect = [
            'code' => $code,
            'target' => $target,
        ];

        return $this;
    }

    /**
     * @param ?array<string, string> $params
     */
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

        if (is_array($notDefinedParams) && count($notDefinedParams) > 0) {
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
        $scriptName = $this->getServerParam('SCRIPT_NAME');

        if (!is_string($scriptName)) {
            throw new RuntimeException();
        }

        $path = $this->getDirname($scriptName);

        $route = '';

        if (trim($path) !== '') {
            $route .= ('/' . $path);
        }
        $route .= ('/' . $relative);

        return $route;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setCookieParam(string $key, string $value, int $expires = 0, array $options = []): self
    {
        $this->sendCookie($key, $value, ['expires' => $expires] + $this->getCookieOptions($options));

        // Visible to the rest of this request at once
        $this->cookie[$key] = $value;

        return $this;
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

    /**
     * A cookie's attributes: the call's over the configuration's `cookies` block over the defaults — the whole
     * site, not readable by scripts, not sent with cross-site subrequests (Lax), and Secure — `secure` null —
     * exactly when the request came over https.
     *
     * @param array<string, mixed> $options
     *
     * @return array{path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'}
     */
    protected function getCookieOptions(array $options): array
    {
        $path = '/';
        $domain = '';
        $secure = null;
        $httpOnly = true;
        $sameSite = 'Lax';

        foreach ([$this->getCookieDefaults(), $options] as $layer) {
            foreach ($layer as $name => $value) {
                if ($name === 'path' && is_string($value) && !static::hasControlCharacter($value)) {
                    $path = $value;
                } elseif ($name === 'domain' && is_string($value) && !static::hasControlCharacter($value)) {
                    $domain = $value;
                } elseif ($name === 'secure' && ($value === null || is_bool($value))) {
                    $secure = $value;
                } elseif ($name === 'httponly' && is_bool($value)) {
                    $httpOnly = $value;
                } elseif ($name === 'samesite' && ($value === 'Lax' || $value === 'Strict' || $value === 'None')) {
                    $sameSite = $value;
                } else {
                    throw new InvalidArgumentException('Unknown or malformed cookie attribute ' . $name . '.');
                }
            }
        }

        $secure ??= $this->isHttpsRequest();

        if ($sameSite === 'None' && !$secure) {
            throw new InvalidArgumentException('A SameSite=None cookie must be Secure.');
        }

        return [
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
    }

    /**
     * The configuration's `cookies` block: the attributes every cookie of the application gets unless the call
     * names its own. Empty when the request runs without a bean factory.
     *
     * @return array<string, mixed>
     */
    protected function getCookieDefaults(): array
    {
        if ($this->__beanFactory === null) {
            return [];
        }

        $config = $this->getBeanFactory()->get('Config');
        $cookies = is_array($config)
            ? ($config['cookies'] ?? [])
            : [];

        if (!is_array($cookies)) {
            throw new InvalidArgumentException('The configuration\'s cookies block must be an array.');
        }

        $defaults = [];

        foreach ($cookies as $name => $value) {
            $defaults[(string)$name] = $value;
        }

        return $defaults;
    }

    /**
     * The path and query of a Referer whose origin is this request's host (scheme http or https; the host and the
     * port compared with the Host header, a default port counting as none), null for any other.
     */
    protected function getRefererPathOnThisHost(string $referer): ?string
    {
        $httpHost = $this->getServerParam('HTTP_HOST');

        if (!is_string($httpHost) || trim($httpHost) === '') {
            return null;
        }

        $parts = parse_url($referer);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $defaultPort = $scheme === 'https'
            ? 443
            : 80;
        $origin = strtolower($parts['host']);

        if (isset($parts['port']) && $parts['port'] !== $defaultPort) {
            $origin .= ':' . $parts['port'];
        }

        $host = strtolower(trim($httpHost));

        if (str_ends_with($host, ':' . $defaultPort)) {
            $host = substr($host, 0, -strlen(':' . $defaultPort));
        }

        if (!hash_equals($host, $origin)) {
            return null;
        }

        return ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    protected function getRoute(): string
    {
        $route = $this->getServerParam('REQUEST_URI');

        if (!is_string($route)) {
            throw new RuntimeException();
        }

        // Remove beginning slashes, just to be sure
        $route = ltrim($route, '/');

        // Get the base path
        $scriptName = $this->getServerParam('SCRIPT_NAME');

        if (!is_string($scriptName)) {
            throw new RuntimeException();
        }

        $base = $this->getDirname($scriptName);

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

    /** Whether the web server says the request came over TLS (its HTTPS variable, "off" meaning not). */
    protected function isHttpsRequest(): bool
    {
        $https = $this->getServerParam('HTTPS');

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    /**
     * Hands a cookie to PHP: the one place a cookie leaves the request, so that a test can record it instead.
     *
     * @param array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'Strict'|'None'} $options
     */
    protected function sendCookie(string $key, string $value, array $options): void
    {
        setcookie($key, $value, $options);
    }

    protected function getDirname(string $path): string
    {
        // replace backslashes with slashes (windows)
        $path = str_replace('\\', '/', $path);
        // remove trailing slashes
        $path = trim($path, '/');
        // explode for slashes
        $path = explode('/', $path);

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
