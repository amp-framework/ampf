<?php

declare(strict_types=1);

namespace ampf\services\translator\impl;

use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\services\translator\TranslatorService;
use RuntimeException;

/** @phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter */
class DefaultTranslatorService implements BeanFactoryAccess, TranslatorService
{
    use DefaultBeanFactoryAccess;

    /** @var ?array<string, string> */
    protected ?array $_config = null;

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

    /** @param ?string[] $args */
    public function translate(string $key, ?array $args = null): ?string
    {
        if (trim($key) === '') {
            throw new RuntimeException();
        }

        $value = $this->getValue($key);
        if ($value === null) {
            return null;
        }

        if (is_array($args) && count($args) > 0) {
            $value = vsprintf($value, $args);
        }

        return $value;
    }

    /** @param array<string, mixed> $config */
    protected function setConfig(array $config): void
    {
        if (count($config) < 1) {
            throw new RuntimeException();
        }

        if (!isset($config['translation.dir'])) {
            throw new RuntimeException();
        }

        $transFile = ($config['translation.dir'] . '/' . $this->getLanguage() . '.php');
        if (!file_exists($transFile)) {
            throw new RuntimeException();
        }

        ob_start();
        $this->_config = require $transFile;
        ob_end_clean();
    }

    /** @return array<string, string> */
    protected function getConfig(): array
    {
        if ($this->_config === null) {
            $this->setConfig($this->getBeanFactory()->get('Config'));
        }

        if ($this->_config === null) {
            throw new RuntimeException();
        }

        return $this->_config;
    }

    protected function getValue(string $key): mixed
    {
        $config = $this->getConfig();
        if (!isset($config[$key])) {
            return $key;
        }

        return $config[$key];
    }
}
