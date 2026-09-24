<?php

declare(strict_types=1);

namespace ampf\View;

use DateTime;

/** A template with its variables, and the helpers its template uses (formats, translations, escaping). */
interface ViewInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function render(string $view): string;

    public function reset(): void;

    /**
     * @param ?array<string, mixed> $params
     */
    public function subRender(string $viewID, ?array $params = null): string;

    public function formatNumber(
        float|int|string $number,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
    ): string;

    /**
     * @param DateTime|numeric|null $time Either a \DateTime instance or a numeric representing an UNIX timestamp
     * @param string|null $format A \DateTime::format compatible string
     */
    public function formatTime(mixed $time = null, ?string $format = null): string;

    /**
     * The translation of the key with the arguments put in as they are (vsprintf()): for arguments that already are
     * the view's output format (markup, a formatted number). Text from anywhere else goes through te().
     *
     * @param ?list<string> $args
     */
    public function t(string $key, ?array $args = null): string;

    /**
     * The translation of the key with every argument escaped for the view's output first (escape()): for arguments
     * that are text — a name, a title, anything a user or another system wrote.
     *
     * @param ?list<string> $args
     */
    public function te(string $key, ?array $args = null): string;

    /**
     * @param ?array<string, mixed> $params
     */
    public function subRoute(string $controllerBean, ?array $params = null): string;

    public function escape(string $string): string;
}
