<?php

declare(strict_types=1);

namespace ampf\services\translator;

interface TranslatorService
{
    public function getKey(string $translation, bool $ignoreCase = true): ?string;

    public function getLanguage(): ?string;

    public function setLanguage(string $language): void;

    /**
     * @param ?list<string> $args
     */
    public function translate(string $key, ?array $args = null): ?string;
}
