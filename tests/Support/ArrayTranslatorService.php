<?php

declare(strict_types=1);

namespace ampfTest\Support;

use ampf\services\translator\TranslatorService;

/**
 * Translations from an array, put together as DefaultTranslatorService does (vsprintf() over the text).
 *
 * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
 */
final class ArrayTranslatorService implements TranslatorService
{
    /**
     * @param array<string, string> $texts
     */
    public function __construct(private readonly array $texts)
    {
    }

    public function getKey(string $translation, bool $ignoreCase = true): ?string
    {
        $key = array_search($translation, $this->texts, true);

        return is_string($key)
            ? $key
            : null;
    }

    public function getLanguage(): string
    {
        return 'en_GB';
    }

    public function setLanguage(string $language): void
    {
        // One language only
    }

    /**
     * @param ?list<string> $args
     */
    public function translate(string $key, ?array $args = null): string
    {
        $text = $this->texts[$key] ?? $key;

        return $args === null
            ? $text
            : vsprintf($text, $args);
    }
}
