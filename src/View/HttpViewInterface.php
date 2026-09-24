<?php

declare(strict_types=1);

namespace ampf\View;

/** A view whose templates render HTML: escaping for HTML, the links of routes and assets, the submitted values. */
interface HttpViewInterface extends ViewInterface
{
    public function getAssetLink(string $relativeLink): string;

    /**
     * @param ?array<string, scalar|null> $params
     */
    public function getActionLink(string $routeID, ?array $params = null, bool $addToken = false): string;

    /**
     * A submitted value as text — the form's first, then the query string's; "" when absent: what a form shown again
     * puts back into its fields.
     */
    public function getParamString(string $name): string;
}
