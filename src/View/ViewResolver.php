<?php

declare(strict_types=1);

namespace ampf\View;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use RuntimeException;

/**
 * Templates under the configuration's `viewDirectory`: a name of letters, digits, `_`, `.` and `-` per path segment,
 * never `..`, for a file that exists.
 */
class ViewResolver implements BeanFactoryAccessInterface, ViewResolverInterface
{
    use BeanFactoryAccess;

    protected ?string $viewDirectory = null;

    /**
     * @throws RuntimeException for a name that is no template's, and for a template that does not exist
     */
    public function getViewFilename(string $view): string
    {
        if (!$this->isValidFilename($view)) {
            throw new RuntimeException(
                'The template name ' . $view . ' has a segment other than letters, digits and _.-, or with "..".',
            );
        }

        $path = $this->getViewDirectory() . '/' . $view;

        if (!is_file($path)) {
            throw new RuntimeException('There is no template ' . $view . '.');
        }

        return $path;
    }

    /**
     * The directory of the templates: the bean 'Config''s `viewDirectory`, unless one was set.
     *
     * @throws RuntimeException when the configuration names no directory that exists
     */
    public function getViewDirectory(): string
    {
        if ($this->viewDirectory === null) {
            $viewDirectory = $this->getBeanFactory()->getConfig()['viewDirectory'] ?? null;

            if (!is_string($viewDirectory)) {
                throw new RuntimeException(
                    'The configuration\'s viewDirectory must name a directory, not '
                    . get_debug_type($viewDirectory) . '.',
                );
            }

            $this->viewDirectory = $this->resolveDirectory($viewDirectory);
        }

        return $this->viewDirectory;
    }

    /**
     * @param array{viewDirectory: ?string} $config
     *
     * @throws RuntimeException when the configuration names no directory that exists
     */
    public function setConfig(array $config): void
    {
        $this->viewDirectory = $this->resolveDirectory(
            $config['viewDirectory'] ?? throw new RuntimeException('The configuration has no viewDirectory.'),
        );
    }

    protected function isValidFilename(string $filename): bool
    {
        // A backslash separates segments too (Windows)
        foreach (explode('/', str_replace('\\', '/', $filename)) as $segment) {
            // D: "$" ends the segment, it does not match before a final line feed
            if (str_contains($segment, '..') || preg_match('/^[A-Za-z0-9_.\-]+$/D', $segment) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws RuntimeException for a directory that does not exist
     */
    protected function resolveDirectory(string $directory): string
    {
        $realPath = realpath($directory);

        if ($realPath === false || !is_dir($realPath)) {
            throw new RuntimeException('The view directory ' . $directory . ' does not exist.');
        }

        return $realPath;
    }
}
