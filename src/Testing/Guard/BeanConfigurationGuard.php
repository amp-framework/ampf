<?php

declare(strict_types=1);

namespace ampf\Testing\Guard;

use ampf\Controller\ControllerInterface;
use ampf\Router\RouteResolver;

/**
 * The beans and the routes of an application agree, which the framework would only find out at the first request that
 * needs them: every bean of its config/default.php is keyed by a type its class is — a singleton by an interface, so
 * that a test or another application can configure another class under it —; every route of each transport names a
 * bean that is a controller; and no route comes after a catch-all, which would take it first. An application extends it
 * in one class that names its project root (projectRoot()).
 */
abstract class BeanConfigurationGuard extends AbstractGuard
{
    /** Routes only a catch-all takes all of: the empty one, and one of every kind of character a route holds. */
    protected const array EVERY_KIND_OF_ROUTE = ['', 'Any/route-1_2.3~!$&\'()*+,;=:@%2F'];

    /**
     * The transports whose routes are checked: an application without a command line has the web's only.
     *
     * @return list<string>
     */
    protected static function transports(): array
    {
        return ['http', 'cli'];
    }

    /** Whether the framework's route resolver gives the route every route of EVERY_KIND_OF_ROUTE. */
    protected static function takesEveryRoute(string $id, mixed $route): bool
    {
        $resolver = new RouteResolver();
        $resolver->setConfig(['routes' => [$id => $route]]);

        foreach (self::EVERY_KIND_OF_ROUTE as $any) {
            if ($resolver->getRouteIDByRoutePattern($any) === null) {
                return false;
            }
        }

        return true;
    }

    public function testEveryBeanIsKeyedByATypeItsClassImplements(): void
    {
        $beans = static::configurationFile('default.php')['beans'] ?? [];
        self::assertIsArray($beans, 'The beans of config/default.php are no array.');
        $problems = [];

        foreach ($beans as $id => $definition) {
            $definition = is_array($definition)
                ? $definition
                : [];
            $class = $definition['class'] ?? null;

            if (!is_string($class)) {
                $problems[] = 'The bean ' . $id . ' names no class.';
            } elseif (!interface_exists($id) && !class_exists($id)) {
                $problems[] = 'The bean id ' . $id . ' is no type.';
            } elseif (!is_a($class, $id, true)) {
                $problems[] = 'The class ' . $class . ' of the bean ' . $id . ' is no ' . $id . '.';
            } elseif (($definition['scope'] ?? null) !== 'prototype' && !interface_exists($id)) {
                $problems[] = 'The singleton ' . $id . ' is keyed by a class, not by an interface.';
            }
        }

        self::assertEmpty($problems, implode(PHP_EOL, $problems));
    }

    public function testEveryRouteNamesABeanThatIsAController(): void
    {
        $problems = [];

        foreach (static::transports() as $transport) {
            $config = static::configuration($transport);
            $beans = $config['beans'] ?? [];
            self::assertIsArray($beans, 'The beans of the configuration are no array.');

            foreach (static::routes($config) as $id => $route) {
                $definition = $beans[$route['controller']] ?? null;
                $class = is_array($definition)
                    ? $definition['class'] ?? null
                    : null;
                $named = 'The ' . $transport . ' route ' . $id . ' names the bean ' . $route['controller'];

                if (!is_array($definition)) {
                    $problems[] = $named . ', which has no definition.';
                } elseif (!is_string($class) || !is_a($class, ControllerInterface::class, true)) {
                    $problems[] = $named . ', which is no controller.';
                }
            }
        }

        self::assertEmpty($problems, implode(PHP_EOL, $problems));
    }

    public function testNoRouteFollowsACatchAll(): void
    {
        $problems = [];

        foreach (static::transports() as $transport) {
            $catchAll = null;

            foreach (static::routes(static::configuration($transport)) as $id => $route) {
                if ($catchAll !== null) {
                    $problems[] = 'The ' . $transport . ' route ' . $id . ' comes after the catch-all ' . $catchAll
                        . ', which takes every route.';
                } elseif (static::takesEveryRoute($id, $route)) {
                    $catchAll = $id;
                }
            }
        }

        self::assertEmpty($problems, implode(PHP_EOL, $problems));
    }
}
