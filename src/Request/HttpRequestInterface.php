<?php

declare(strict_types=1);

namespace ampf\Request;

use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * An HTTP request and the response being prepared for it: the input as PHP received it (GET, POST, cookies, server
 * variables, the raw body), the route it names, and the status, headers, cookies and body or redirect that flush()
 * sends.
 */
interface HttpRequestInterface
{
    /**
     * @throws RuntimeException for a blank name or value, a name that is no token, a value with a control character
     */
    public function addHeader(string $key, string $value): self;

    /**
     * Whether the request came from one of this site's own pages or was typed or bookmarked — the browser's
     * `Sec-Fetch-Site` is `same-origin` or `none` —, or from a browser that does not send the header. A link or a
     * form on another site says `cross-site` (or `same-site`, another origin of the site): such a request may show a
     * page, but should not change anything on the user's behalf.
     */
    public function comesFromThisSite(): bool;

    /**
     * Deletes the cookie in the browser (an empty, expired cookie with the attributes it was set with) and in this
     * request; nothing when the request does not carry it.
     *
     * @param array<string, mixed> $options the attributes, as setCookieParam() takes them
     *
     * @throws InvalidArgumentException for an unknown or malformed attribute
     */
    public function destroyCookieParam(string $key, array $options = []): self;

    /**
     * Sends the response: the status code, the headers, then the redirect or the body. Every header is checked
     * before the first byte goes out; PHP's own `X-Powered-By` is not sent.
     */
    public function flush(): self;

    /**
     * @return list<stdClass>
     */
    public function getAcceptedLanguages(): array;

    /**
     * The link of a route: the route's own parameters go into its path (percent-encoded), the others into the query
     * string. A parameter may be any scalar (an entity's id); null is an empty value.
     *
     * @param ?array<string, scalar|null> $params
     */
    public function getActionLink(
        string $routeID,
        ?array $params = null,
        bool $addToken = false,
        ?string $hashParam = null,
    ): string;

    /** The request's raw body (a JSON document an API client sent, say), "" when it has none. Read once. */
    public function getBody(): string;

    public function getController(): ?string;

    public function getCookieParam(string $key): mixed;

    public function getGetParam(string $key): mixed;

    /**
     * A GET parameter as text: the string the client sent, "" when it is absent or has another shape (an array from
     * `name[]=`).
     */
    public function getGetString(string $key): string;

    public function getLink(string $relative): string;

    /**
     * The parameter from the form when the form sent it, else from the query string, as text (see getGetString()).
     */
    public function getParamString(string $key): string;

    /**
     * A parameter sent as a list (`name[]=`) as the strings it holds; a single value is a one-item list, an absent
     * parameter an empty one; entries that are not strings are dropped. POST first, then GET.
     *
     * @return list<string>
     */
    public function getParamStrings(string $key): array;

    public function getPostParam(string $key): mixed;

    /** A POST parameter as text; see getGetString(). */
    public function getPostString(string $key): string;

    /**
     * A POST parameter sent as a map (`name[key]=`) with its string values, keyed as sent; empty unless it is an
     * array.
     *
     * @return array<int|string, string>
     */
    public function getPostStringMap(string $key): array;

    /**
     * Returns the HTTP referer of this request (if any) localized, which here means
     * cleared by the HTTP host and subdirectory.
     */
    public function getRefererLocalized(): ?string;

    /**
     * Returns the HTTP referer of this request (if any).
     */
    public function getRefererRaw(): ?string;

    public function getResponse(): string;

    public function getRouteID(): ?string;

    /**
     * @return ?array<string, string>
     */
    public function getRouteParams(): ?array;

    public function getServerParam(string $key): mixed;

    public function hasCookieParam(string $key): bool;

    public function hasCorrectToken(): bool;

    public function hasGetParam(string $key): bool;

    public function hasPostParam(string $key): bool;

    public function hasServerParam(string $key): bool;

    public function isPostRequest(): bool;

    public function isRedirect(): bool;

    /**
     * @param ?array<string, scalar|null> $params
     */
    public function setRedirect(
        string $routeID,
        ?array $params = null,
        ?int $code = null,
        ?bool $addToken = null,
        ?string $hashParam = null,
    ): self;

    /**
     * Sets the cookie in the browser and in this request at once. $options names the attributes that differ from
     * the configuration's `cookies` block and the defaults: `path` ("/"), `domain` (""), `secure` (null: exactly
     * when the request came over https), `httponly` (true), `samesite` ("Lax"; "Strict" or "None", which needs
     * `secure`). $expires is a UNIX time; 0 makes a cookie that ends with the browser session.
     *
     * @param array<string, mixed> $options
     *
     * @throws InvalidArgumentException for an unknown or malformed attribute
     */
    public function setCookieParam(string $key, string $value, int $expires = 0, array $options = []): self;

    public function setResponse(string $response): self;

    public function setStatusCode(int $statusCode): self;
}
