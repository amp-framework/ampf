<?php

declare(strict_types=1);

namespace ampf\Service\Asset;

use ampf\Request\HttpRequestInterface;
use RuntimeException;

/**
 * The versions of the assets of an application whose web server serves them itself: the stylesheets, scripts and images
 * below the document root. An address that carries the version of its file is another when the content is, so that a
 * browser may keep the file for a year and still fetch the new one the day it changes.
 */
interface AssetServiceInterface
{
    /**
     * The address of the file at the path below the assets directory (`css/app.css`), as the request builds it: the
     * application's base path, the address the web server serves the assets under (`assets.path`, `assets` without it),
     * the path, and the file's version as the query parameter `v`.
     *
     * @throws RuntimeException when the configuration names no assets.directory, or the file is not there
     */
    public function link(HttpRequestInterface $request, string $path): string;
}
