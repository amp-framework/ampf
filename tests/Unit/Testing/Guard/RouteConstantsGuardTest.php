<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing\Guard;

use ampf\Testing\Guard\AbstractGuard;
use ampf\Testing\Guard\RouteConstantsGuard;
use ampf\Tests\Fixtures\RuleAbidingApp\Constant\RouteConstants as AbidingRouteConstants;
use ampf\Tests\Fixtures\RuleBreakingApp\Constant\NumberedRouteConstants;
use ampf\Tests\Fixtures\RuleBreakingApp\Constant\RouteConstants as BreakingRouteConstants;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The guard over an application whose route constants are its routes (tests/Fixtures/RuleAbidingApp: the constants
 * in another order than the routes, and one constant of no route's), and one whose constants miss routes, name one that
 * is gone and share a value, and whose catch-alls are not last (tests/Fixtures/RuleBreakingApp). A guard is a TestCase,
 * which takes its name: PHP-CS-Fixer writes `new class('name')`, PSR-12 `new class ('name')`.
 *
 * @phpcs:disable PSR12.Classes.AnonClassDeclaration.SpaceAfterKeyword
 */
#[CoversClass(AbstractGuard::class)]
#[CoversClass(RouteConstantsGuard::class)]
final class RouteConstantsGuardTest extends TestCase
{
    /** The guard over an application whose route constants are its routes. */
    private static function abiding(): RouteConstantsGuard
    {
        return new class('abiding') extends RouteConstantsGuard {
            protected static function routeConstantsClass(): string
            {
                return AbidingRouteConstants::class;
            }

            protected static function catchAllRouteId(): string
            {
                return AbidingRouteConstants::ROUTE_ID_NOT_FOUND;
            }

            protected static function cliCatchAllRouteId(): string
            {
                return AbidingRouteConstants::CLI_ROUTE_ID_HELP;
            }

            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures/RuleAbidingApp';
            }
        };
    }

    /** The guard over an application whose route constants are not its routes. */
    private static function breaking(): RouteConstantsGuard
    {
        return new class('breaking') extends RouteConstantsGuard {
            protected static function routeConstantsClass(): string
            {
                return BreakingRouteConstants::class;
            }

            protected static function catchAllRouteId(): string
            {
                return 'not-found';
            }

            protected static function cliCatchAllRouteId(): string
            {
                return BreakingRouteConstants::CLI_ROUTE_ID_HELP;
            }

            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures/RuleBreakingApp';
            }
        };
    }

    public function testAnApplicationWhoseConstantsAreItsRoutesPasses(): void
    {
        $guard = self::abiding();

        // The guard's own assertions pass
        $guard->testTheWebConstantsAreTheWebRoutes();
        $guard->testTheCommandLineConstantsAreTheCommandLineRoutes();
        $guard->testNoTwoRouteConstantsShareAValue();
        $guard->testTheWebCatchAllIsTheLastWebRoute();
        $guard->testTheCommandLineCatchAllIsTheLastCommandLineRoute();
    }

    public function testConstantsThatAreNotTheWebRoutesFail(): void
    {
        $this->assertFailure(
            'The constants ROUTE_ID_* of ' . BreakingRouteConstants::class . ' are not the routes of config/http.php.'
            . PHP_EOL . 'Failed asserting that two arrays are identical.',
            static fn () => self::breaking()->testTheWebConstantsAreTheWebRoutes(),
        );
    }

    public function testConstantsThatAreNotTheCommandLinesRoutesFail(): void
    {
        $this->assertFailure(
            'The constants CLI_ROUTE_ID_* of ' . BreakingRouteConstants::class . ' are not the routes of config/cli.php.'
            . PHP_EOL . 'Failed asserting that two arrays are identical.',
            static fn () => self::breaking()->testTheCommandLineConstantsAreTheCommandLineRoutes(),
        );
    }

    public function testTwoConstantsOfOneValueFail(): void
    {
        $this->assertFailure(
            'ROUTE_ID_HOME and CLI_ROUTE_ID_HOME share the value home.' . PHP_EOL
            . 'Failed asserting that an array is empty.',
            static fn () => self::breaking()->testNoTwoRouteConstantsShareAValue(),
        );
    }

    public function testACatchAllThatIsNotTheLastRouteFails(): void
    {
        $this->assertFailure(
            'The catch-all not-found is not the last route of config/http.php.' . PHP_EOL
            . 'Failed asserting that two strings are identical.',
            static fn () => self::breaking()->testTheWebCatchAllIsTheLastWebRoute(),
        );
        $this->assertFailure(
            'The catch-all help is not the last route of config/cli.php.' . PHP_EOL
            . 'Failed asserting that two strings are identical.',
            static fn () => self::breaking()->testTheCommandLineCatchAllIsTheLastCommandLineRoute(),
        );
    }

    public function testARouteConstantThatIsNoTextFails(): void
    {
        $guard = new class('numbered') extends RouteConstantsGuard {
            protected static function routeConstantsClass(): string
            {
                return NumberedRouteConstants::class;
            }

            protected static function catchAllRouteId(): string
            {
                return 'not-found';
            }

            protected static function cliCatchAllRouteId(): string
            {
                return 'help';
            }

            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures/RuleBreakingApp';
            }
        };

        $this->assertFailure(
            'The route constant ROUTE_ID_COUNT is no text.' . PHP_EOL . 'Failed asserting that 3 is of type string.',
            static fn () => $guard->testNoTwoRouteConstantsShareAValue(),
        );
    }

    public function testASubclassChangesTheStepsOfTheGuard(): void
    {
        $guard = new class('seam') extends RouteConstantsGuard {
            /**
             * The protected methods called, in their order.
             *
             * @var list<string>
             */
            private static array $calls = [];

            /**
             * @return list<string>
             */
            public static function calls(): array
            {
                return self::$calls;
            }

            protected static function routeConstantsClass(): string
            {
                return AbidingRouteConstants::class;
            }

            protected static function catchAllRouteId(): string
            {
                return AbidingRouteConstants::ROUTE_ID_NOT_FOUND;
            }

            protected static function cliCatchAllRouteId(): string
            {
                return AbidingRouteConstants::CLI_ROUTE_ID_HELP;
            }

            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures/RuleAbidingApp';
            }

            /**
             * @return list<string>
             */
            protected static function routeIds(string $transport): array
            {
                self::$calls[] = 'routeIds ' . $transport;

                return parent::routeIds($transport);
            }

            /**
             * @return array<string, string>
             */
            protected static function routeConstants(): array
            {
                self::$calls[] = 'routeConstants';

                return parent::routeConstants();
            }

            protected function assertTheConstantsAreTheRoutes(string $prefix, string $transport): void
            {
                self::$calls[] = 'assertTheConstantsAreTheRoutes ' . $prefix . ' ' . $transport;

                parent::assertTheConstantsAreTheRoutes($prefix, $transport);
            }
        };

        $guard->testTheWebConstantsAreTheWebRoutes();
        $guard->testTheCommandLineCatchAllIsTheLastCommandLineRoute();

        self::assertSame(
            ['assertTheConstantsAreTheRoutes ROUTE_ID_ http', 'routeIds http', 'routeConstants', 'routeIds cli'],
            $guard::calls(),
        );
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
