<?php

declare(strict_types=1);

namespace ampf\requests;

interface HttpRequest
{
    public function addHeader(string $key, string $value): self;

    public function destroyCookieParam(string $key): self;

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

    public function setResponse(string $response): self;

    public function setStatusCode(int $statusCode): self;
}
