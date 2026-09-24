<?php

declare(strict_types=1);

namespace ampf\Tests\Integration;

use ampf\Bean\BeanFactory;
use ampf\Bootstrap\ApplicationContext;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fixture application's configuration merged over the framework's, as its entry points merge it: every bean
 * of it can be created, and is an instance of its class.
 */
#[CoversNothing]
final class WiringTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTransports(): iterable
    {
        yield 'web' => ['http.php'];
        yield 'command line' => ['cli.php'];
    }

    #[DataProvider('provideTransports')]
    public function testEveryBeanOfTheMergedConfigurationCanBeCreated(string $transport): void
    {
        $root = dirname(__DIR__, 2);
        $config = ApplicationContext::boot([
            $root . '/config/default.php',
            $root . '/config/' . $transport,
            $root . '/tests/Fixtures/App/config/default.php',
            $root . '/tests/Fixtures/App/config/' . $transport,
        ]);
        self::assertIsArray($config['beans']);
        $beanFactory = new BeanFactory($config);

        foreach ($config['beans'] as $id => $definition) {
            self::assertIsString($id);
            self::assertIsArray($definition);
            $bean = $beanFactory->get($id);
            self::assertIsObject($bean, $id);
            self::assertSame($definition['class'], $bean::class, $id);
        }

        self::assertGreaterThan(15, count($config['beans']));
    }
}
