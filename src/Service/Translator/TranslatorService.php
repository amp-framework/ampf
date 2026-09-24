<?php

declare(strict_types=1);

namespace ampf\Service\Translator;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use InvalidArgumentException;
use RuntimeException;

/**
 * The texts of `translation.dir`/<language>.php, a file that returns a `string => string` array, loaded at the first
 * translation after setLanguage(); a key without a text translates to itself. A language is a code such as `de` or
 * `en_GB` — never a path.
 */
class TranslatorService implements BeanFactoryAccessInterface, TranslatorServiceInterface
{
    use BeanFactoryAccess;

    /**
     * @var ?array<string, string>
     */
    protected ?array $translations = null;

    protected ?string $language = null;

    public function getKey(string $translation, bool $ignoreCase = true): ?string
    {
        foreach ($this->getConfig() as $key => $value) {
            if ($ignoreCase ? mb_strtolower($value) === mb_strtolower($translation) : $value === $translation) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @throws RuntimeException before a language is set
     */
    public function getLanguage(): ?string
    {
        return $this->language ?? throw new RuntimeException('The translator has no language: setLanguage() first.');
    }

    /**
     * @throws InvalidArgumentException for a language that is no language code
     */
    public function setLanguage(string $language): void
    {
        // A language names a file: letters, then parts of letters and digits after "_" or "-", and nothing else
        if (preg_match('/^[A-Za-z]{2,8}(?:[_-][A-Za-z0-9]{1,8})*$/D', $language) !== 1) {
            throw new InvalidArgumentException('A language is a code such as de or en_GB, not ' . $language . '.');
        }

        if ($language === $this->language) {
            return;
        }

        $this->language = $language;
        $this->translations = null;
    }

    /**
     * @param ?list<string> $args
     *
     * @throws RuntimeException for a blank key
     */
    public function translate(string $key, ?array $args = null): ?string
    {
        if (trim($key) === '') {
            throw new RuntimeException('A blank key has no translation.');
        }

        $value = $this->getConfig()[$key] ?? $key;

        return $args === null || $args === []
            ? $value
            : vsprintf($value, $args);
    }

    /**
     * The language's texts, loaded at the first use.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when the configuration names no directory of translation files
     */
    protected function getConfig(): array
    {
        if ($this->translations !== null) {
            return $this->translations;
        }

        $directory = $this->getBeanFactory()->getConfig()['translation.dir'] ?? null;

        if (!is_string($directory)) {
            throw new RuntimeException(
                'The configuration\'s translation.dir must name a directory, not ' . get_debug_type($directory) . '.',
            );
        }

        return $this->translations = $this->loadTexts($directory);
    }

    /**
     * The texts of the language's file in the directory.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException for a directory without the language's file, and a file that returns no texts
     */
    protected function loadTexts(string $directory): array
    {
        $file = $directory . '/' . $this->getLanguage() . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('There is no translation file ' . $file . '.');
        }

        // What the file prints is not part of any response
        ob_start();

        try {
            $texts = (static fn (string $__file): mixed => require $__file)($file);
        } finally {
            ob_end_clean();
        }

        if (!is_array($texts)) {
            throw new RuntimeException('The translation file ' . $file . ' returns no array.');
        }

        foreach ($texts as $key => $text) {
            if (!is_string($key) || !is_string($text)) {
                throw new RuntimeException(
                    'The translation file ' . $file . ' maps the key ' . $key . ' to something other than a text.',
                );
            }
        }

        return $texts;
    }
}
