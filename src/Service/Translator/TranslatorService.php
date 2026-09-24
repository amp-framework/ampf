<?php

declare(strict_types=1);

namespace ampf\Service\Translator;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use RuntimeException;

/**
 * The texts of `translation.dir`/<language>.php, a file that returns a `string => string` array, loaded at the first
 * translation after setLanguage(); a key without a text translates to itself.
 *
 * @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
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
        $config = $this->getConfig();

        foreach ($config as $key => $value) {
            if (mb_strtolower($value) === mb_strtolower($translation)) {
                return $key;
            }
        }

        return null;
    }

    public function getLanguage(): ?string
    {
        if ($this->language === null) {
            throw new RuntimeException('No language set');
        }

        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        if (trim($language) === '') {
            throw new RuntimeException();
        }

        $this->language = $language;
    }

    /**
     * @param ?list<string> $args
     */
    public function translate(string $key, ?array $args = null): ?string
    {
        if (trim($key) === '') {
            throw new RuntimeException();
        }

        $value = $this->getValue($key);

        if (is_array($args) && count($args) > 0) {
            $value = vsprintf($value, $args);
        }

        return $value;
    }

    /**
     * @param array{"translation.dir": ?string} $config
     */
    protected function setConfig(array $config): void
    {
        if (!isset($config['translation.dir'])) {
            throw new RuntimeException();
        }

        $transFile = ($config['translation.dir'] . '/' . $this->getLanguage() . '.php');

        if (!file_exists($transFile)) {
            throw new RuntimeException();
        }

        ob_start();
        $transConfig = require $transFile;
        ob_end_clean();

        if (!is_array($transConfig)) {
            throw new RuntimeException();
        }

        foreach ($transConfig as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new RuntimeException();
            }
        }

        $this->translations = $transConfig;
    }

    /**
     * @return array<string, string>
     */
    protected function getConfig(): array
    {
        if ($this->translations === null) {
            $config = $this->getBeanFactory()->get('Config');

            if (!is_array($config) || !isset($config['translation.dir'])) {
                throw new RuntimeException();
            }

            $translationDir = $config['translation.dir'];

            if (!is_string($translationDir)) {
                throw new RuntimeException();
            }

            $this->setConfig(['translation.dir' => $translationDir]);
        }

        if ($this->translations === null) {
            throw new RuntimeException();
        }

        return $this->translations;
    }

    protected function getValue(string $key): string
    {
        $config = $this->getConfig();

        if (!isset($config[$key])) {
            return $key;
        }

        return $config[$key];
    }
}
