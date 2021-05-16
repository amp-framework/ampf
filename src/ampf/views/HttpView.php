<?php

declare(strict_types=1);

namespace ampf\views;

interface HttpView extends View
{
    public function getAssetLink(string $relativeLink): string;

    public function getActionLink(string $routeID, ?array $params = null, bool $addToken = false): string;
}
