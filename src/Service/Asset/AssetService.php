<?php

declare(strict_types=1);

namespace ampf\Service\Asset;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\Request\HttpRequestInterface;
use RuntimeException;

/**
 * Gives the files of the directory the configuration's `assets` block names their version: the first 16 characters of
 * the SHA-256 of the content, so that a changed file is another address. A file is read once for a request. The web
 * server serves the files; nothing here does.
 *
 * The block is the application's: `directory` is the absolute path of the folder the web server serves (`public/assets`),
 * `path` the address below the application's base path that it serves it under (`assets` when left out).
 */
class AssetService implements AssetServiceInterface, BeanFactoryAccessInterface
{
    use BeanFactoryAccess;

    /**
     * What was looked up, by path: the version of a file.
     *
     * @var array<string, string>
     */
    protected array $versions = [];

    public function link(HttpRequestInterface $request, string $path): string
    {
        $config = $this->getBeanFactory()->getConfig()['assets'] ?? [];
        assert(is_array($config));
        $directory = $config['directory'] ?? throw new RuntimeException('The configuration names no assets.directory.');
        $address = $config['path'] ?? 'assets';
        assert(is_string($directory) && is_string($address));

        $this->versions[$path] ??= $this->versionOf($directory . '/' . $path);

        return $request->getLink($address . '/' . $path) . '?v=' . $this->versions[$path];
    }

    /**
     * The version of a file.
     *
     * @throws RuntimeException for a file that is not there or cannot be read
     */
    protected function versionOf(string $file): string
    {
        $hash = is_file($file)
            ? hash_file('sha256', $file)
            : false;

        if ($hash === false) {
            throw new RuntimeException('The asset ' . $file . ' does not exist or cannot be read.');
        }

        return substr($hash, 0, 16);
    }
}
