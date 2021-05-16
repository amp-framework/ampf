<?php

declare(strict_types=1);

namespace ampf\services\xsrfToken;

interface XsrfTokenService
{
    public function getNewToken(): string;

    public function getTokenIDForRequest(): string;

    public function isCorrectToken(string $token): bool;
}
