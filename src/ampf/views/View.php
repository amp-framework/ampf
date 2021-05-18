<?php

declare(strict_types=1);

namespace ampf\views;

interface View
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function render(string $view): string;

    public function reset(): void;

    /** @param ?array<string, mixed> $params */
    public function subRender(string $viewID, ?array $params = null): string;

    public function formatNumber(
        string $number,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
    ): string;

    /**
     * @param \DateTime|numeric|null $time   Either a \DateTime instance or a numeric representing an UNIX timestamp
     * @param string|null            $format A \DateTime::format compatible string
     */
    public function formatTime(mixed $time = null, ?string $format = null): string;

    /** @param ?string[] $args */
    public function t(string $key, ?array $args = null): string;

    /** @param ?array<string, mixed> $params */
    public function subRoute(string $controllerBean, ?array $params = null): string;

    public function escape(mixed $string): string;
}
