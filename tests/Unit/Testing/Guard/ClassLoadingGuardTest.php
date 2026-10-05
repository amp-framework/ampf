<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing\Guard;

use ampf\Testing\Guard\AbstractGuard;
use ampf\Testing\Guard\ClassLoadingGuard;
use ampf\Tests\Fixtures\GeneratorApp\GreeterInterface;
use ampf\Tests\Fixtures\GeneratorApp\Service\ClockInterface;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The guard over source trees: the generator's fixture application, where every file declares the type its path names
 * (an interface, a trait and classes among them), the framework's own test support at the default source directory,
 * and one whose files declare another type or the type in another case. A guard is a TestCase, which takes its name:
 * PHP-CS-Fixer writes `new class('name')`, PSR-12 `new class ('name')`.
 *
 * @phpcs:disable PSR12.Classes.AnonClassDeclaration.SpaceAfterKeyword
 */
#[CoversClass(AbstractGuard::class)]
#[CoversClass(ClassLoadingGuard::class)]
final class ClassLoadingGuardTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../../Fixtures';

    /**
     * The source files the guard finds under the directory of tests/Fixtures, by their paths there, with their types
     * under the namespace.
     *
     * @return array<string, string>
     */
    private static function listing(string $directory, string $namespace): array
    {
        $guard = new class('listing') extends ClassLoadingGuard {
            private static string $directory = '';

            private static string $namespace = '';

            /**
             * @return array<string, string>
             */
            public static function list(string $directory, string $namespace): array
            {
                self::$directory = $directory;
                self::$namespace = $namespace;

                return self::sourceFiles();
            }

            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures';
            }

            protected static function sourceDirectory(): string
            {
                return self::$directory;
            }

            protected static function sourceNamespace(): string
            {
                return self::$namespace;
            }
        };
        $prefix = self::FIXTURES . '/' . $directory . '/';
        $files = [];

        foreach ($guard::list($directory, $namespace) as $file => $type) {
            self::assertStringStartsWith($prefix, $file);
            self::assertStringStartsWith($namespace . '\\', $type);
            $files[substr($file, strlen($prefix))] = substr($type, strlen($namespace) + 1);
        }

        return $files;
    }

    public function testEveryPhpFileUnderTheSourceDirectoryIsListedWithTheTypeItsPathNames(): void
    {
        $files = self::listing('GeneratorApp', 'ampf\Tests\Fixtures\GeneratorApp');
        ksort($files);

        self::assertSame(
            [
                'Doctrine/Entity/Archive/Deep/NoteEntity.php' => 'Doctrine\Entity\Archive\Deep\NoteEntity',
                'Doctrine/Entity/Audited.php' => 'Doctrine\Entity\Audited',
                'Doctrine/Entity/LogEntity.php' => 'Doctrine\Entity\LogEntity',
                'Doctrine/Entity/TimestampedInterface.php' => 'Doctrine\Entity\TimestampedInterface',
                'Doctrine/Entity/UserEntity.php' => 'Doctrine\Entity\UserEntity',
                'Doctrine/Repository/Archive/Deep/NoteRepo.php' => 'Doctrine\Repository\Archive\Deep\NoteRepo',
                'Doctrine/Repository/UserRepo.php' => 'Doctrine\Repository\UserRepo',
                'GreeterInterface.php' => 'GreeterInterface',
                'Service/ClockInterface.php' => 'Service\ClockInterface',
                'Service/Mail/MailQueueInterface.php' => 'Service\Mail\MailQueueInterface',
                'Service/Mail/MailService.php' => 'Service\Mail\MailService',
                'Service/Mail/MailServiceInterface.php' => 'Service\Mail\MailServiceInterface',
                'Service/Width/TheAssertionIsOneCharacterTooLongHereInterface.php' => 'Service\Width\TheAssertionIsOneCharacterTooLongHereInterface',
                'Service/Width/TheSetterSignatureFillsTheLineWhollyInterface.php' => 'Service\Width\TheSetterSignatureFillsTheLineWhollyInterface',
            ],
            $files,
        );

        $files = self::listing('MisnamedTypes', 'ampf\Tests\Fixtures\MisnamedTypes');
        ksort($files);

        self::assertSame(
            ['Helper/Strings.php' => 'Helper\Strings', 'Service/Mailer.php' => 'Service\Mailer'],
            $files,
            'a file of another kind is no source file',
        );
    }

    public function testATreeWhoseFilesDeclareTheirTypesPasses(): void
    {
        $guard = new class('sound') extends ClassLoadingGuard {
            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures';
            }

            protected static function sourceDirectory(): string
            {
                return 'GeneratorApp';
            }

            protected static function sourceNamespace(): string
            {
                return 'ampf\Tests\Fixtures\GeneratorApp';
            }
        };

        // The guard's own assertion passes
        $guard->testEveryFileDeclaresTheTypeItsPathNames();
    }

    public function testTheSourceDirectoryIsSrcUnlessTold(): void
    {
        $guard = new class('framework') extends ClassLoadingGuard {
            /**
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

            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../..';
            }

            protected static function sourceNamespace(): string
            {
                return 'ampf';
            }

            protected static function sourceDirectory(): string
            {
                self::$calls[] = 'sourceDirectory';

                return parent::sourceDirectory();
            }

            /**
             * The framework's own test support: the rest of the tree is ClassLoadingTest's.
             *
             * @return array<string, string>
             */
            protected static function sourceFiles(): array
            {
                self::$calls[] = 'sourceFiles';

                return array_filter(
                    parent::sourceFiles(),
                    static fn (string $type): bool => str_starts_with($type, 'ampf\Testing\Guard\\'),
                );
            }
        };

        $guard->testEveryFileDeclaresTheTypeItsPathNames();

        self::assertSame(['sourceFiles', 'sourceDirectory'], $guard::calls());
    }

    public function testFilesThatDeclareAnotherTypeOrTheirsInAnotherCaseFail(): void
    {
        $guard = new class('misnamed') extends ClassLoadingGuard {
            protected static function projectRoot(): string
            {
                return __DIR__ . '/../../../Fixtures';
            }

            protected static function sourceDirectory(): string
            {
                return 'MisnamedTypes';
            }

            protected static function sourceNamespace(): string
            {
                return 'ampf\Tests\Fixtures\MisnamedTypes';
            }
        };
        $fixture = self::FIXTURES . '/MisnamedTypes';

        // The only test that looks the type of Helper/Strings.php up: a second look would declare its class twice
        $this->assertFailure(
            'The file ' . $fixture . '/Helper/Strings.php does not declare ampf\Tests\Fixtures\MisnamedTypes\Helper\Strings,'
            . ' the type its path names.' . PHP_EOL
            . 'The file ' . $fixture . '/Service/Mailer.php declares ampf\Tests\Fixtures\MisnamedTypes\service\Mailer, but'
            . ' its path names ampf\Tests\Fixtures\MisnamedTypes\Service\Mailer.' . PHP_EOL
            . 'Failed asserting that an array is empty.',
            $guard,
        );
    }

    public function testATypeDeclaredInAnotherFileFailsAndTheFilesComeInTheirOrder(): void
    {
        $guard = new class('elsewhere') extends ClassLoadingGuard {
            protected static function projectRoot(): string
            {
                return __DIR__;
            }

            protected static function sourceNamespace(): string
            {
                return 'ampf\Tests\Fixtures\GeneratorApp';
            }

            /**
             * Two files whose types are declared elsewhere, the last one first.
             *
             * @return array<string, string>
             */
            protected static function sourceFiles(): array
            {
                return ['/b/Greeter.php' => GreeterInterface::class, '/a/Clock.php' => ClockInterface::class];
            }
        };
        $generatorApp = (string)realpath(self::FIXTURES . '/GeneratorApp');

        $this->assertFailure(
            ClockInterface::class . ' is declared in ' . $generatorApp . '/Service/ClockInterface.php, not in'
            . ' /a/Clock.php.' . PHP_EOL
            . GreeterInterface::class . ' is declared in ' . $generatorApp . '/GreeterInterface.php, not in'
            . ' /b/Greeter.php.' . PHP_EOL
            . 'Failed asserting that an array is empty.',
            $guard,
        );
    }

    /** The guard's test fails with exactly this message. */
    private function assertFailure(string $message, ClassLoadingGuard $guard): void
    {
        try {
            $guard->testEveryFileDeclaresTheTypeItsPathNames();
        } catch (AssertionFailedError $e) {
            self::assertSame($message, $e->getMessage());

            return;
        }

        self::fail('The guard passed.');
    }
}
