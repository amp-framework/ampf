<?php

declare(strict_types=1);

namespace ampf\View;

/** Maps a template's name to its file. */
interface ViewResolverInterface
{
    public function getViewFilename(string $view): string;
}
