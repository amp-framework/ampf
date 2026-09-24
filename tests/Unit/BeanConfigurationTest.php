<?php

declare(strict_types=1);

namespace ampf\Tests\Unit;

use ampf\Request\CliRequestInterface;
use ampf\Request\HttpRequestInterface;
use ampf\Router\CliRouterInterface;
use ampf\Router\HttpRouterInterface;
use ampf\View\CliViewInterface;
use ampf\View\HttpViewInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The framework's bean configuration: every service in config/default.php is keyed by an interface its class
 * implements (an application replaces it under the same key), and each transport file fills the four roles —
 * 'Router', 'Request', 'RequestStub', 'View' — with classes of that transport. A typo on either side would only show
 * as a "No configuration for bean" or a TypeError at the first use.
 */
#[CoversNothing]
final class BeanConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string, class-string>}>
     */
    public static function provideTransports(): iterable
    {
        yield 'http' => ['http.php', [
            'Router' => HttpRouterInterface::class,
            'Request' => HttpRequestInterface::class,
            'RequestStub' => HttpRequestInterface::class,
            'View' => HttpViewInterface::class,
        ]];
        yield 'cli' => ['cli.php', [
            'Router' => CliRouterInterface::class,
            'Request' => CliRequestInterface::class,
            'RequestStub' => CliRequestInterface::class,
            'View' => CliViewInterface::class,
        ]];
    }

    public function testEveryServiceIsKeyedByAnInterfaceItsClassImplements(): void
    {
        $beans = $this->beans('default.php');
        self::assertNotEmpty($beans);

        foreach ($beans as $id => $definition) {
            self::assertTrue(interface_exists($id), sprintf('bean id "%s" is not an interface', $id));
            self::assertTrue(
                is_a($definition['class'], $id, true),
                sprintf('%s does not implement its bean id %s', $definition['class'], $id),
            );
            self::assertNotSame('prototype', $definition['scope'] ?? null, $id . ' is a singleton');
        }
    }

    /**
     * @param array<string, class-string> $roles
     */
    #[DataProvider('provideTransports')]
    public function testTheTransportFillsItsRoles(string $file, array $roles): void
    {
        $beans = $this->beans($file);

        self::assertSame(array_keys($roles), array_keys($beans));

        foreach ($roles as $role => $type) {
            self::assertTrue(
                is_a($beans[$role]['class'], $type, true),
                sprintf('%s of %s is no %s', $role, $file, $type),
            );
        }

        self::assertSame('Request', $beans['RequestStub']['parent'] ?? null);
        self::assertSame('prototype', $beans['RequestStub']['scope'] ?? null, 'a new stub for each sub-request');
        self::assertSame('prototype', $beans['View']['scope'] ?? null, 'a new view for each template');
    }

    /**
     * @return array<string, array{class: string, scope?: string, parent?: string}>
     */
    private function beans(string $file): array
    {
        $config = require dirname(__DIR__, 2) . '/config/' . $file;
        self::assertIsArray($config);
        self::assertIsArray($config['beans']);

        $beans = [];

        foreach ($config['beans'] as $id => $definition) {
            self::assertIsString($id);
            self::assertIsArray($definition);
            self::assertIsString($definition['class']);
            $beans[$id] = $definition;
        }

        /** @var array<string, array{class: string, scope?: string, parent?: string}> $beans */
        return $beans;
    }
}
