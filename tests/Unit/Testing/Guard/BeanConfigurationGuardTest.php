<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing\Guard;

use ampf\Service\TimeL10n\TimeL10nService;
use ampf\Service\TimeL10n\TimeL10nServiceInterface;
use ampf\Testing\Guard\AbstractGuard;
use ampf\Testing\Guard\BeanConfigurationGuard;
use ampf\Tests\Fixtures\RuleBreakingApp\Service\Mailer\MailerInterface;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The guard over an application that keeps the rules of beans and routes (tests/Fixtures/RuleAbidingApp), the fixture
 * application (tests/Fixtures/App: no beans of its own, no catch-all), and one that breaks each rule
 * (tests/Fixtures/RuleBreakingApp). A guard is a TestCase, which takes its name: PHP-CS-Fixer writes
 * `new class('name')`, PSR-12 `new class ('name')`.
 *
 * @phpcs:disable PSR12.Classes.AnonClassDeclaration.SpaceAfterKeyword
 */
#[CoversClass(AbstractGuard::class)]
#[CoversClass(BeanConfigurationGuard::class)]
final class BeanConfigurationGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providePatterns(): iterable
    {
        yield 'everything' => ['.*', true];
        yield 'everything, captured' => ['(?P<path>.*)', true];
        yield 'everything but the home page' => ['.+', false];
        yield 'the home page' => ['', false];
        yield 'one segment of anything' => ['[^/]*', false];
        yield 'a page' => ['notes', false];
    }

    /** The guard over an application that keeps the rules. */
    private static function abiding(): BeanConfigurationGuard
    {
        return new class('abiding') extends BeanConfigurationGuard {
            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures/RuleAbidingApp';
            }
        };
    }

    /** The guard over the fixture application, which defines no beans in its config/default.php. */
    private static function application(): BeanConfigurationGuard
    {
        return new class('application') extends BeanConfigurationGuard {
            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures/App';
            }
        };
    }

    /** The guard over an application that breaks each rule. */
    private static function breaking(): BeanConfigurationGuard
    {
        return new class('breaking') extends BeanConfigurationGuard {
            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures/RuleBreakingApp';
            }
        };
    }

    public function testAnApplicationThatKeepsTheRulesPasses(): void
    {
        // The guard's own assertions pass
        foreach ([self::abiding(), self::application()] as $guard) {
            $guard->testEveryBeanIsKeyedByATypeItsClassImplements();
            $guard->testEveryRouteNamesABeanThatIsAController();
            $guard->testNoRouteFollowsACatchAll();
        }
    }

    public function testABeanKeyedByNoTypeOrByATypeItsClassIsNotFails(): void
    {
        $this->assertFailure(
            'The bean id Mailer is no type.' . PHP_EOL
            . 'The class ' . TimeL10nService::class . ' of the bean ' . MailerInterface::class . ' is no '
            . MailerInterface::class . '.' . PHP_EOL
            . 'The singleton ' . TimeL10nService::class . ' is keyed by a class, not by an interface.' . PHP_EOL
            . 'The bean Broken names no class.' . PHP_EOL
            . 'The bean NotADefinition names no class.' . PHP_EOL
            . 'Failed asserting that an array is empty.',
            static fn () => self::breaking()->testEveryBeanIsKeyedByATypeItsClassImplements(),
        );
    }

    public function testARouteThatNamesNoControllerBeanFails(): void
    {
        $this->assertFailure(
            'The http route missing names the bean MissingController, which has no definition.' . PHP_EOL
            . 'The http route view names the bean View, which is no controller.' . PHP_EOL
            . 'The http route time names the bean ' . TimeL10nServiceInterface::class . ', which is no controller.'
            . PHP_EOL . 'Failed asserting that an array is empty.',
            static fn () => self::breaking()->testEveryRouteNamesABeanThatIsAController(),
        );
    }

    public function testARouteAfterACatchAllFails(): void
    {
        $this->assertFailure(
            'The http route late comes after the catch-all not-found, which takes every route.' . PHP_EOL
            . 'The cli route greet comes after the catch-all help, which takes every route.' . PHP_EOL
            . 'Failed asserting that an array is empty.',
            static fn () => self::breaking()->testNoRouteFollowsACatchAll(),
        );
    }

    public function testAnApplicationNamesItsTransports(): void
    {
        $guard = new class('command line') extends BeanConfigurationGuard {
            /**
             * @return list<string>
             */
            protected static function transports(): array
            {
                return ['cli'];
            }

            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures/RuleBreakingApp';
            }
        };

        $guard->testEveryRouteNamesABeanThatIsAController();

        $this->assertFailure(
            'The cli route greet comes after the catch-all help, which takes every route.' . PHP_EOL
            . 'Failed asserting that an array is empty.',
            static fn () => $guard->testNoRouteFollowsACatchAll(),
        );
    }

    public function testMalformedRoutesAreRefusedAsTheFrameworkRefusesThem(): void
    {
        $guard = new class('malformed') extends BeanConfigurationGuard {
            /**
             * @param array<string, mixed> $config
             *
             * @return array<string, array{pattern: string, controller: string}>
             */
            public static function routesOf(array $config): array
            {
                return self::routes($config);
            }

            protected static function projectRoot(): string
            {
                return __DIR__;
            }
        };

        self::assertSame(
            ['home' => ['pattern' => '', 'controller' => 'HomeController']],
            $guard::routesOf(['routes' => ['home' => ['pattern' => '', 'controller' => 'HomeController']]]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The route notes must name its pattern and its controller, and nothing else.');

        $guard::routesOf(['routes' => ['notes' => ['pattern' => 'notes']]]);
    }

    #[DataProvider('providePatterns')]
    public function testACatchAllIsARouteThatTakesEveryRoute(string $pattern, bool $catchAll): void
    {
        $guard = new class('patterns') extends BeanConfigurationGuard {
            public static function isCatchAll(string $pattern): bool
            {
                return self::takesEveryRoute('route', ['pattern' => $pattern, 'controller' => 'Controller']);
            }

            protected static function projectRoot(): string
            {
                return __DIR__;
            }
        };

        self::assertSame($catchAll, $guard::isCatchAll($pattern));
    }

    /**
     * The guard's test fails with exactly this message.
     *
     * @param callable(): void $test
     */
    private function assertFailure(string $message, callable $test): void
    {
        try {
            $test();
        } catch (AssertionFailedError $e) {
            self::assertSame($message, $e->getMessage());

            return;
        }

        self::fail('The guard passed.');
    }
}
