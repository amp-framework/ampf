<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing\Guard;

use ampf\Testing\Guard\AbstractGuard;
use ampf\Testing\Guard\BeanAccessGuard;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The guard over two applications whose access traits are the generator's output (tests/Fixtures/RuleAbidingApp and
 * the fixture application, a repository's trait), and one whose trait of a service is missing and whose trait of a
 * bean that is gone was not deleted (tests/Fixtures/RuleBreakingApp). A guard is a TestCase, which takes its name:
 * PHP-CS-Fixer writes `new class('name')`, PSR-12 `new class ('name')`.
 *
 * @phpcs:disable PSR12.Classes.AnonClassDeclaration.SpaceAfterKeyword
 */
#[CoversClass(AbstractGuard::class)]
#[CoversClass(BeanAccessGuard::class)]
final class BeanAccessGuardTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../../Fixtures';

    /** The guard over the fixture application of the name. */
    private static function guard(string $application): BeanAccessGuard
    {
        return match ($application) {
            'App' => new class('App') extends BeanAccessGuard {
                protected static function projectRoot(): string
                {
                    return __DIR__ . '/../../../Fixtures/App';
                }
            },
            'RuleAbidingApp' => new class('RuleAbidingApp') extends BeanAccessGuard {
                protected static function projectRoot(): string
                {
                    return __DIR__ . '/../../../Fixtures/RuleAbidingApp';
                }
            },
            default => new class('RuleBreakingApp') extends BeanAccessGuard {
                protected static function projectRoot(): string
                {
                    return __DIR__ . '/../../../Fixtures/RuleBreakingApp';
                }
            },
        };
    }

    public function testAnApplicationWhoseTraitsAreGeneratedPasses(): void
    {
        // The guard's own assertion passes
        self::guard('RuleAbidingApp')->testTheAccessTraitsAreTheGeneratorsOutput();
        self::guard('App')->testTheAccessTraitsAreTheGeneratorsOutput();
    }

    public function testAMissingTraitAndAStaleOneFail(): void
    {
        try {
            self::guard('RuleBreakingApp')->testTheAccessTraitsAreTheGeneratorsOutput();
            self::fail('The guard passed.');
        } catch (AssertionFailedError $e) {
            self::assertSame(
                'The access traits differ from what the generator writes (BeanAccessGeneratorController):' . PHP_EOL
                . 'new     tests/Fixtures/RuleBreakingApp/BeanAccess/Service/MailerAccess.php' . PHP_EOL
                . 'stale   tests/Fixtures/RuleBreakingApp/BeanAccess/OldAccess.php (not generated any more; delete it'
                . ' by hand)' . PHP_EOL
                . '1 traits, 1 would change' . PHP_EOL
                . 'Failed asserting that 1 is identical to 0.',
                $e->getMessage(),
            );
        }

        self::assertFileDoesNotExist(
            self::FIXTURES . '/RuleBreakingApp/BeanAccess/Service/MailerAccess.php',
            'checked only',
        );
    }
}
