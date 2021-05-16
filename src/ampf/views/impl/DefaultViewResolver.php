<?php

declare(strict_types=1);

namespace ampf\views\impl;

use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use ampf\views\ViewResolver;
use Exception;
use RuntimeException;

class DefaultViewResolver implements BeanFactoryAccess, ViewResolver
{
    use DefaultBeanFactoryAccess;

    protected ?string $_viewDirectory = null;

    public function getViewFilename(string $view): string
    {
        if (!$this->isValidFilename($view)) {
            throw new Exception();
        }

        $path = ($this->getViewDirectory() . '/' . $view);
        if (!file_exists($path)) {
            throw new Exception();
        }

        return $path;
    }

    public function getViewDirectory(): string
    {
        if ($this->_viewDirectory === null) {
            $this->setConfig($this->getBeanFactory()->get('Config'));
        }

        return $this->_viewDirectory;
    }

    /** @param array<string, mixed> $config */
    public function setConfig(array $config): void
    {
        if (count($config) < 1) {
            throw new RuntimeException();
        }

        if (!isset($config['viewDirectory'])) {
            throw new RuntimeException();
        }

        $this->_viewDirectory = realpath($config['viewDirectory']);
    }

    protected function isValidFilename(string $filename): bool
    {
        // replace backslashes with slashes (windows)
        $filename = str_replace('\\', '/', $filename);

        // explode by slashes
        $array = explode('/', $filename);
        foreach ($array as $value) {
            // more than 1 dot is not allowed
            if (strpos($value, '..') !== false) {
                return false;
            }

            // A-Z a-z 0-9 _ . -
            if (!preg_match('/^[A-Za-z0-9_\.\-]+$/', $value)) {
                return false;
            }
        }

        return true;
    }
}
