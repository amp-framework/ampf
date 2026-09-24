<?php

declare(strict_types=1);

namespace ampf\requests;

interface HttpRequest
{
    /**
     * @throws \RuntimeException for a blank name or value, a name that is no token, a value with a control character
     */
    public function addHeader(string $key, string $value): self;

    /**
     * Deletes the cookie in the browser (an empty, expired cookie with the attributes it was set with) and in this
     * request; nothing when the request does not carry it.
     *
     * @param array<string, mixed> $options the attributes, as setCookieParam() takes them
     *
     * @throws \InvalidArgumentException for an unknown or malformed attribute
     */
    public function destroyCookieParam(string $key, array $options = []): self;

    public function flush(): self;

    /**
     * @return list<\stdClass>
     */
    public function getAcceptedLanguages(): array;

    /**
     * @param ?array<string, string> $params
     */
    public function getActionLink(
        string $routeID,
        ?array $params = null,
        bool $addToken = false,
        ?string $hashParam = null,
    ): string;

    public function getController(): ?string;

    public function getCookieParam(string $key): mixed;

    public function getGetParam(string $key): mixed;

    public function getLink(string $relative): string;

    public function getPostParam(string $key): mixed;

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
     * @param array<string, string> $params
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
     * @throws \InvalidArgumentException for an unknown or malformed attribute
     */
    public function setCookieParam(string $key, string $value, int $expires = 0, array $options = []): self;

    public function setResponse(string $response): self;

    public function setStatusCode(int $statusCode): self;
}
