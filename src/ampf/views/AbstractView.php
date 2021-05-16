<?php

declare(strict_types=1);

namespace ampf\views;

use ampf\beans\access\TranslatorServiceAccess;
use ampf\beans\access\ViewResolverAccess;
use ampf\beans\BeanFactoryAccess;
use ampf\beans\impl\DefaultBeanFactoryAccess;
use DateTime;
use DateTimeZone;
use Exception;

abstract class AbstractView implements BeanFactoryAccess, View
{
    use DefaultBeanFactoryAccess;
    use TranslatorServiceAccess;
    use ViewResolverAccess;

    /** @var array<string, mixed> */
    protected array $memory = [];

    protected DateTimeZone $timezone_utc;

    protected DateTimeZone $timezone_local;

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
            if ($key !== 'path' && $key !== 'this') {
                ${$key} = $value;
            }
        }

        ob_start();
        require $path;

        return ob_get_clean();
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

        // set the params
        foreach ($params as $key => $value) {
            $view->set($key, $value);
        }

        // render the output and return it
        return $view->render($viewID);
    }

    public function formatNumber(
        mixed $number,
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

        return number_format($number, $decimals, $decPoint, $thousandsSep);
    }

    public function formatTime(mixed $time = null, ?string $format = null): string
    {
        // If not instanceof DateTime, try to create from unix timestamp
        if (!($time instanceof DateTime)) {
            $time = DateTime::createFromFormat('U', $time, $this->getTimeZoneUTC());
            if (!$time) {
                throw new Exception();
            }
        }
        assert($time instanceof DateTime);

        if ($format === null) {
            $format = 'd.m.Y H:i';
        }

        // Convert to local timezone
        $datetime = clone $time;
        $datetime->setTimezone($this->getTimeZoneLocal());

        return $datetime->format($format);
    }

    public function t(string $key, ?array $args = null): string
    {
        return $this->getTranslatorService()->translate($key, $args);
    }

    protected function getTimeZoneUTC(): DateTimeZone
    {
        if ($this->timezone_utc === null) {
            $this->timezone_utc = new DateTimeZone('UTC');
        }

        return $this->timezone_utc;
    }

    protected function getTimeZoneLocal(): DateTimeZone
    {
        if ($this->timezone_local === null) {
            $this->timezone_local = new DateTimeZone(date_default_timezone_get());
        }

        return $this->timezone_local;
    }
}
