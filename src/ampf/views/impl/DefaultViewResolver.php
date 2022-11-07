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
            $config = $this->getBeanFactory()->get('Config');
            if (!is_array($config) || !isset($config['viewDirectory'])) {
                throw new RuntimeException();
            }
            $this->setConfig($config);
        }

        if ($this->_viewDirectory === null) {
            throw new RuntimeException();
        }

        return $this->_viewDirectory;
    }

    /** @param array{viewDirectory: ?string} $config */
    public function setConfig(array $config): void
    {
        if (!isset($config['viewDirectory'])) {
            throw new RuntimeException();
        }

        $viewDirectory = realpath($config['viewDirectory']);
        if ($viewDirectory === false) {
            throw new RuntimeException();
        }

        $this->_viewDirectory = $viewDirectory;
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
