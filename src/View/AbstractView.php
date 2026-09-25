<?php

declare(strict_types=1);

namespace ampf\View;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\Service\TranslatorServiceAccess;
use ampf\BeanAccess\ViewResolverAccess;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

/**
 * A view's variables and their template: render() resolves the template file (the ViewResolverInterface bean) and
 * returns what the template printed. The template runs in a scope of its own: its local variables are the view's
 * variables — every one whose name is a variable name —, and `$this` is the view.
 */
abstract class AbstractView implements BeanFactoryAccessInterface, ViewInterface
{
    use BeanFactoryAccess;
    use TranslatorServiceAccess;
    use ViewResolverAccess;

    /**
     * @var array<string, mixed>
     */
    protected array $memory = [];

    /**
     * The zone of formatTime(), taken at the view's first format.
     */
    protected ?DateTimeZone $timezoneLocal = null;

    /**
     * The time as a DateTimeImmutable: a DateTimeInterface as it is, a Unix timestamp in UTC. A template may hand in
     * anything, so anything is checked.
     *
     * @throws RuntimeException for a time that is neither a DateTimeInterface nor a Unix timestamp
     */
    protected static function toDateTime(mixed $time): DateTimeImmutable
    {
        if ($time instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($time);
        }

        $dateTime = is_numeric($time)
            ? DateTimeImmutable::createFromFormat('U', (string)$time)
            : false;

        if ($dateTime === false) {
            throw new RuntimeException(
                'A time is a DateTimeInterface or a Unix timestamp, not '
                . (is_scalar($time) ? var_export($time, true) : get_debug_type($time)) . '.',
            );
        }

        return $dateTime;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->memory[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->memory[$key]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->memory[$key] = $value;
    }

    /**
     * @throws RuntimeException when there is no such template, or when it closed the view's buffer
     */
    public function render(string $view): string
    {
        return $this->capture(
            // No parameter and nothing inherited: the template's scope holds its variables and $this, and nothing else
            // phpcs:ignore SlevomatCodingStandard.Functions.StaticClosure.ClosureNotStatic -- the template's $this
            function (): void {
                // @phpstan-ignore argument.type (render() hands in the view's array, without a name of its own)
                extract(func_get_arg(1), EXTR_SKIP);

                require func_get_arg(0);
            },
            $this->getViewResolver()->getViewFilename($view),
            $this->memory,
        );
    }

    public function reset(): void
    {
        $this->memory = [];
    }

    /**
     * The template rendered by a new view (the bean 'View', a prototype) that holds the parameters only.
     *
     * @param ?array<string, mixed> $params
     *
     * @throws RuntimeException when the bean 'View' is no view
     */
    public function subRender(string $viewID, ?array $params = null): string
    {
        $view = $this->getBeanFactory()->get('View');

        if (!$view instanceof ViewInterface) {
            throw new RuntimeException('The bean View is no view, but ' . get_debug_type($view) . '.');
        }

        foreach ($params ?? [] as $key => $value) {
            $view->set($key, $value);
        }

        return $view->render($viewID);
    }

    public function formatNumber(
        float|int|string $number,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
    ): string {
        return number_format((float)$number, $decimals ?? 0, $decPoint ?? '.', $thousandsSep ?? ' ');
    }

    /**
     * @param DateTimeInterface|numeric $time
     *
     * @throws RuntimeException for a time that is neither a DateTimeInterface nor a Unix timestamp
     */
    public function formatTime(mixed $time, ?string $format = null): string
    {
        return static::toDateTime($time)->setTimezone($this->getTimeZoneLocal())->format($format ?? 'd.m.Y H:i');
    }

    /**
     * @param ?list<string> $args
     */
    public function t(string $key, ?array $args = null): string
    {
        return $this->getTranslatorService()->translate($key, $args) ?? '';
    }

    /**
     * @param ?list<string> $args
     */
    public function te(string $key, ?array $args = null): string
    {
        return $this->t($key, $args === null ? null : array_map($this->escape(...), $args));
    }

    /**
     * What the callable prints, taken out of the output — all of it, and whatever buffer the callable left open,
     * when it fails.
     *
     * @throws RuntimeException when the callable closed the buffer it printed into
     */
    protected function capture(callable $print, mixed ...$arguments): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            $print(...$arguments);

            // Closed, the buffer's output is lost, and what surrounds it — another view's, PHP's own — is no output of
            // this callable's
            if (ob_get_level() <= $level) {
                throw new RuntimeException(
                    'The output was printed into no buffer of its own: its buffer was closed while printing.',
                );
            }

            // The buffer started here is there: ob_get_contents() is false without any
            return (string)ob_get_contents();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /**
     * The time zone formatTime() shows a time in: PHP's default time zone at the view's first format, kept for the
     * view's every other — a view for a user of another zone returns that one.
     */
    protected function getTimeZoneLocal(): DateTimeZone
    {
        return $this->timezoneLocal ??= new DateTimeZone(date_default_timezone_get());
    }
}
