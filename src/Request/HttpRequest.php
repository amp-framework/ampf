<?php

declare(strict_types=1);

namespace ampf\Request;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\RouteResolverAccess;
use ampf\BeanAccess\Service\XsrfTokenServiceAccess;
use ampf\Helper\Functions;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * The HTTP request of PHP's superglobals, read once when the request is created (scalars as strings, arrays kept),
 * and the response the controllers prepare on it until flush() sends it.
 *
 * @phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
 */
class HttpRequest implements BeanFactoryAccessInterface, HttpRequestInterface
{
    use BeanFactoryAccess;
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

    /**
     * The raw body, read at the first getBody().
     */
    protected ?string $body = null;

    /** Whether the text holds a C0 control character or DEL, a horizontal tab excepted. */
    protected static function hasControlCharacter(string $text): bool
    {
        return preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $text) === 1;
    }

    /** The value as text when it is one, else "" (an absent parameter, an array from `name[]=`). */
    protected static function asString(mixed $value): string
    {
        return is_string($value)
            ? $value
            : '';
    }

    /**
     * Link parameters as the route resolver takes them: every scalar as its text, null as "".
     *
     * @param array<string, scalar|null> $params
     *
     * @return array<string, string>
     */
    protected static function stringifyParams(array $params): array
    {
        $strings = [];

        foreach ($params as $key => $value) {
            $strings[$key] = (string)$value;
        }

        return $strings;
    }

    /**
     * The path without the base path: the base path's segments at its start are cut off, a longer segment that only
     * starts like the base path (`application` under `app`) is not.
     */
    protected static function withoutBasePath(string $path, string $basePath): string
    {
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            return substr($path, strlen($basePath));
        }

        return $path;
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
            throw new RuntimeException('A header needs a name and a value.');
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

    public function comesFromThisSite(): bool
    {
        $site = $this->getServerParam('HTTP_SEC_FETCH_SITE');

        return !is_string($site) || in_array(strtolower(trim($site)), ['', 'same-origin', 'none'], true);
    }

    /**
     * @phpstan-impure
     */
    public function flush(): self
    {
        // PHP's own header, which a development php.ini's expose_php adds: nothing a client needs to know
        $this->removeHeader('X-Powered-By');

        // Everything is checked before the first byte goes out: a refused header ends the request as an error
        foreach ($this->headers as $header) {
            if (static::hasControlCharacter($header)) {
                throw new RuntimeException('A header must not contain a control character.');
            }
        }

        if ($this->responseRedirect !== null && static::hasControlCharacter($this->responseRedirect['target'])) {
            throw new RuntimeException('A redirect target must not contain a control character.');
        }

        $this->sendStatusCode($this->responseStatusCode);

        foreach ($this->headers as $header) {
            $this->sendHeader($header);
        }
        $this->headers = [];

        if ($this->responseRedirect !== null) {
            $this->sendHeader('Location: ' . $this->responseRedirect['target'], $this->responseRedirect['code']);
            $this->responseRedirect = null;
        }

        if ($this->responseBody !== null) {
            echo $this->responseBody;
            $this->responseBody = null;
        }

        return $this;
    }

    /**
     * The languages of the Accept-Language header in their order, each with its quality (1.0 unless the header
     * names one); a range whose quality is malformed or 0 (not acceptable) is left out.
     *
     * @return list<stdClass>
     */
    public function getAcceptedLanguages(): array
    {
        $header = $this->getServerParam('HTTP_ACCEPT_LANGUAGE');
        $results = [];

        foreach (explode(',', is_string($header) ? $header : '') as $range) {
            $parts = explode(';', $range);
            $language = trim($parts[0]);
            $quality = 1.0;

            if ($language === '') {
                continue;
            }

            // The first parameter is the weight, q=0 to q=1 with up to three decimals (RFC 9110, section 12.4.2)
            if (isset($parts[1])) {
                if (preg_match('/^\s*q=(0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)\s*$/iD', $parts[1], $match) !== 1) {
                    continue;
                }

                $quality = (float)$match[1];

                if ($quality === 0.0) {
                    continue;
                }
            }

            $result = new stdClass();
            $result->language = $language;
            $result->quality = $quality;

            $results[] = $result;
        }

        return $results;
    }

    public function hasServerParam(string $key): bool
    {
        return isset($this->server[$key]);
    }

    public function getServerParam(string $key): mixed
    {
        return $this->server[$key] ?? null;
    }

    public function getController(): ?string
    {
        return $this->getRouteResolver()->getControllerByRoutePattern($this->getRoute());
    }

    public function getCookieParam(string $key): mixed
    {
        return $this->cookie[$key] ?? null;
    }

    public function getBody(): string
    {
        // PHP's input stream is read once: a second read would find it empty
        return $this->body ??= (string)file_get_contents('php://input');
    }

    public function getGetString(string $key): string
    {
        return static::asString($this->getGetParam($key));
    }

    public function getPostString(string $key): string
    {
        return static::asString($this->getPostParam($key));
    }

    public function getParamString(string $key): string
    {
        return $this->hasPostParam($key)
            ? $this->getPostString($key)
            : $this->getGetString($key);
    }

    /**
     * @return list<string>
     */
    public function getParamStrings(string $key): array
    {
        $value = $this->hasPostParam($key)
            ? $this->getPostParam($key)
            : $this->getGetParam($key);

        if (is_string($value)) {
            return [$value];
        }

        $strings = [];

        foreach (is_array($value) ? $value : [] as $entry) {
            if (is_string($entry)) {
                $strings[] = $entry;
            }
        }

        return $strings;
    }

    /**
     * @return array<int|string, string>
     */
    public function getPostStringMap(string $key): array
    {
        $value = $this->getPostParam($key);
        $strings = [];

        foreach (is_array($value) ? $value : [] as $index => $entry) {
            if (is_string($entry)) {
                $strings[$index] = $entry;
            }
        }

        return $strings;
    }

    public function getPostParam(string $key): mixed
    {
        return $this->post[$key] ?? null;
    }

    public function hasPostParam(string $key): bool
    {
        return isset($this->post[$key]);
    }

    public function getRefererLocalized(): ?string
    {
        $referer = $this->getRefererRaw();

        if ($referer === null) {
            return null;
        }

        // Ours only when its origin is this request's host: a path of another site means nothing here
        $path = $this->getRefererPathOnThisHost($referer);

        if ($path === null) {
            return null;
        }

        // The route: the path without the application's base path
        $referer = ltrim(static::withoutBasePath(ltrim($path, '/'), $this->getBasePath()), '/');

        return trim($referer) === ''
            ? null
            : $referer;
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
        return $this->responseBody ?? '';
    }

    public function getRouteID(): ?string
    {
        return $this->getRouteResolver()->getRouteIDByRoutePattern($this->getRoute());
    }

    /**
     * @return ?array<string, string>
     */
    public function getRouteParams(): ?array
    {
        return $this->getRouteResolver()->getParamsByRoutePattern($this->getRoute());
    }

    public function hasCorrectToken(): bool
    {
        $tokenService = $this->getXsrfTokenService();

        return $tokenService->isCorrectToken($this->getGetString($tokenService->getTokenIDForRequest()));
    }

    public function hasGetParam(string $key): bool
    {
        return isset($this->get[$key]);
    }

    public function getGetParam(string $key): mixed
    {
        return $this->get[$key] ?? null;
    }

    public function isPostRequest(): bool
    {
        return $this->getServerParam('REQUEST_METHOD') === 'POST';
    }

    public function isRedirect(): bool
    {
        return $this->responseRedirect !== null;
    }

    /**
     * @param ?array<string, scalar|null> $params
     */
    public function setRedirect(
        string $routeID,
        ?array $params = null,
        ?int $code = null,
        ?bool $addToken = null,
        ?string $hashParam = null,
    ): self {
        if ($this->responseBody !== null) {
            throw new RuntimeException('A redirect cannot follow a response body.');
        }

        $code ??= 301;

        if ($code < 300 || $code > 399) {
            throw new InvalidArgumentException('A redirect\'s status is a code from 300 to 399, not ' . $code . '.');
        }

        $target = $this->getActionLink($routeID, $params, $addToken ?? false, $hashParam);

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
     * @param ?array<string, scalar|null> $params
     */
    public function getActionLink(
        string $routeID,
        ?array $params = null,
        ?bool $addToken = false,
        ?string $hashParam = null,
    ): string {
        $params = static::stringifyParams($params ?? []);

        if ($addToken === true) {
            $params[$this->getXsrfTokenService()->getTokenIDForRequest()] = $this->getXsrfTokenService()->getNewToken();
        }

        $link = $this->getRouteResolver()->getRoutePatternByRouteID($routeID, $params)
            ?? throw new RuntimeException('There is no route ' . $routeID . '.');

        $query = [];

        foreach ($this->getRouteResolver()->getNotDefinedParams($routeID, $params) ?? [] as $key => $value) {
            $query[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        if ($query !== []) {
            $link .= '?' . implode('&', $query);
        }

        if (trim($hashParam ?? '') !== '') {
            $link .= '#' . rawurlencode((string)$hashParam);
        }

        return $this->getLink($link);
    }

    public function getLink(string $relative): string
    {
        $basePath = $this->getBasePath();

        return ($basePath === '' ? '' : '/' . $basePath) . '/' . $relative;
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
            throw new RuntimeException('A response body cannot follow a redirect.');
        }

        $this->responseBody = $response;

        return $this;
    }

    public function setStatusCode(int $statusCode): self
    {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new RuntimeException('An HTTP status is a code from 100 to 599, not ' . $statusCode . '.');
        }

        $this->responseStatusCode = $statusCode;

        return $this;
    }

    /**
     * The application's base path: the directory of the script (SCRIPT_NAME) without its slashes, "" at the
     * site's root.
     *
     * @throws RuntimeException when the request has no SCRIPT_NAME
     */
    protected function getBasePath(): string
    {
        $scriptName = $this->getServerParam('SCRIPT_NAME');

        if (!is_string($scriptName)) {
            throw new RuntimeException('The request has no SCRIPT_NAME: its base path is unknown.');
        }

        return $this->getDirname($scriptName);
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

        $cookies = $this->getBeanFactory()->getConfig()['cookies'] ?? [];

        if (!is_array($cookies)) {
            throw new InvalidArgumentException(
                'The configuration\'s cookies must be an array, not ' . get_debug_type($cookies) . '.',
            );
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

    /**
     * The route: REQUEST_URI's path without the query string and without the application's base path.
     *
     * @throws RuntimeException when the request has no REQUEST_URI
     */
    protected function getRoute(): string
    {
        $uri = $this->getServerParam('REQUEST_URI');

        if (!is_string($uri)) {
            throw new RuntimeException('The request has no REQUEST_URI: its route is unknown.');
        }

        $path = ltrim(explode('?', $uri, 2)[0], '/');

        return ltrim(static::withoutBasePath($path, $this->getBasePath()), '/');
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

    /**
     * Hands a header line to PHP, replacing an earlier one of the name; $statusCode is the status that goes with it
     * (a redirect's), 0 for none. With sendStatusCode() and removeHeader() the place a response's head leaves the
     * request, so that a test can record it instead.
     */
    protected function sendHeader(string $header, int $statusCode = 0): void
    {
        header($header, true, $statusCode);
    }

    /** Hands the response's status code to PHP (see sendHeader()). */
    protected function sendStatusCode(int $statusCode): void
    {
        http_response_code($statusCode);
    }

    /** Takes a header PHP added by itself out of the response (see sendHeader()). */
    protected function removeHeader(string $name): void
    {
        header_remove($name);
    }

    /**
     * The directory of a script's path, without its slashes: every segment of the path letters, digits and `_.%-`.
     *
     * @throws RuntimeException for a segment of other characters
     */
    protected function getDirname(string $path): string
    {
        // replace backslashes with slashes (windows)
        $path = str_replace('\\', '/', $path);

        // the segments, without the empty ones ('blub//didub' is 'blub/didub')
        $segments = array_values(
            array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''),
        );

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z0-9_\.%\-]+$/D', $segment) !== 1) {
                throw new RuntimeException(
                    'The script\'s path ' . $path . ' has a segment other than letters, digits and _.%-.',
                );
            }
        }

        // the directory: without the script's own name
        array_pop($segments);

        return implode('/', $segments);
    }
}
