<?php

declare(strict_types=1);

namespace ampf\View;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\Service\TranslatorServiceAccess;
use ampf\BeanAccess\ViewResolverAccess;
use DateTime;
use DateTimeZone;
use RuntimeException;

/**
 * A view's variables and their template: render() resolves the template file (the ViewResolverInterface bean), puts
 * the variables into the template's scope and returns what the template printed — inside the template, `$this` is the
 * view.
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

    protected ?DateTimeZone $timezoneUtc = null;

    protected ?DateTimeZone $timezoneLocal = null;

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->memory[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->memory[$key]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->memory[$key] = $value;
    }

    public function render(string $view): string
    {
        $path = $this->getViewResolver()->getViewFilename($view);

        foreach ($this->memory as $key => $value) {
            if ($key === 'path' || $key === 'this') {
                continue;
            }

            // @phpcs:ignore SlevomatCodingStandard.Variables.DisallowVariableVariable.DisallowedVariableVariable
            ${$key} = $value;
        }

        ob_start();
        require $path;
        $result = ob_get_clean();

        if ($result === false) {
            throw new RuntimeException();
        }

        return $result;
    }

    public function reset(): void
    {
        $this->memory = [];
    }

    public function subRender(string $viewID, ?array $params = null): string
    {
        if (is_null($params)) {
            $params = [];
        }

        // get a new view
        $view = $this->getBeanFactory()->get('View');
        assert($view instanceof ViewInterface);

        // set the params
        foreach ($params as $key => $value) {
            $view->set($key, $value);
        }

        // render the output and return it
        return $view->render($viewID);
    }

    public function formatNumber(
        float|int|string $number,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
    ): string {
        if ($decimals === null) {
            $decimals = 0;
        }

        if ($decPoint === null) {
            $decPoint = '.';
        }

        if ($thousandsSep === null) {
            $thousandsSep = ' ';
        }

        return number_format((float)$number, $decimals, $decPoint, $thousandsSep);
    }

    public function formatTime(mixed $time = null, ?string $format = null): string
    {
        // If not instanceof DateTime, try to create from unix timestamp
        if (!($time instanceof DateTime)) {
            $time = DateTime::createFromFormat('U', (string)$time, $this->getTimeZoneUTC());

            if (!($time instanceof DateTime)) {
                throw new RuntimeException();
            }
        }

        if ($format === null) {
            $format = 'd.m.Y H:i';
        }

        // Convert to local timezone
        $datetime = clone $time;
        $datetime->setTimezone($this->getTimeZoneLocal());

        return $datetime->format($format);
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
        if ($args === null) {
            return $this->t($key);
        }

        $escaped = [];

        foreach ($args as $arg) {
            $escaped[] = $this->escape($arg);
        }

        return $this->t($key, $escaped);
    }

    protected function getTimeZoneUTC(): DateTimeZone
    {
        if ($this->timezoneUtc === null) {
            $this->timezoneUtc = new DateTimeZone('UTC');
        }

        return $this->timezoneUtc;
    }

    protected function getTimeZoneLocal(): DateTimeZone
    {
        if ($this->timezoneLocal === null) {
            $this->timezoneLocal = new DateTimeZone(date_default_timezone_get());
        }

        return $this->timezoneLocal;
    }
}
