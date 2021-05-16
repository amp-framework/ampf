<?php

declare(strict_types=1);

namespace ampf\views;

interface ViewResolver
{
    public function getViewFilename(string $view): string;
}
