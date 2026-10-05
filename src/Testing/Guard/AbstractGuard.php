<?php

declare(strict_types=1);

namespace ampf\Testing\Guard;

use ampf\Bootstrap\ApplicationContext;
use ampf\Router\RouteResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The base of the guards: tests of an application's tree and configuration against ampf's rules, which an application
 * runs by extending a guard in a small class of its tests that names its project root (projectRoot()). A guard fails on
 * a change that nothing else notices before the first request that needs it.
 */
abstract class AbstractGuard extends TestCase
{
    /** The application's directory, the one with config/ in it. */
    abstract protected static function projectRoot(): string;

    /**
     * The configuration of the transport as the application's entry point boots it, without the machine's
     * config/local.php: the framework's config/default.php and config/<transport>.php, then the application's.
     *
     * @return array<string, mixed>
     */
    protected static function configuration(string $transport): array
    {
        $framework = dirname(__DIR__, 3) . '/config/';
        $application = static::projectRoot() . '/config/';

        return ApplicationContext::boot([
            $framework . 'default.php',
            $framework . $transport . '.php',
            $application . 'default.php',
            $application . $transport . '.php',
        ]);
    }

    /**
     * What one of the application's configuration files (`default.php`, `http.php`) returns on its own.
     *
     * @return array<string, mixed>
     */
    protected static function configurationFile(string $file): array
    {
        return ApplicationContext::boot([static::projectRoot() . '/config/' . $file]);
    }

    /**
     * The routes of the configuration, in their order, checked by the framework's route resolver as it checks them at
     * the first request.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, array{pattern: string, controller: string}>
     *
     * @throws RuntimeException for routes that are missing or malformed, naming the route
     */
    protected static function routes(array $config): array
    {
        $resolver = new class extends RouteResolver {
            /**
             * @param array<mixed> $config
             *
             * @return array<string, array{pattern: string, controller: string}>
             */
            public function checked(array $config): array
            {
                return $this->routesOf($config);
            }
        };

        return $resolver->checked($config);
    }
}
