<?php

declare(strict_types=1);

namespace ampf\Service\XsrfToken;

/** One-time tokens a request carries to prove that it comes from a page this site rendered for the session. */
interface XsrfTokenServiceInterface
{
    public function getNewToken(): string;

    public function getTokenIDForRequest(): string;

    public function isCorrectToken(string $token): bool;
}
