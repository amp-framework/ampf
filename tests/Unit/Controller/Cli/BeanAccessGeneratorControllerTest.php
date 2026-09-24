<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Controller\Cli;

use ampf\Bean\BeanFactory;
use ampf\Controller\Cli\BeanAccessGeneratorController;
use ampf\Request\CliRequest;
use ampf\Tests\Fixtures\GeneratorApp\Service\Mail\MailServiceInterface;
use ampf\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(BeanAccessGeneratorController::class)]
final class BeanAccessGeneratorControllerTest extends TestCase
{
    private const string NAMESPACE = 'ampf\Tests\Fixtures\GeneratorApp';

    private TemporaryDirectory $directory;

    /**
     * @return iterable<string, array{array<mixed>|string|null, string}>
     */
    public static function provideArgumentsThatDoNotFit(): iterable
    {
        $missing = 'The configuration\'s beanAccessGenerator must name the generator\'s projectRoot and namespace.';
        $root = sys_get_temp_dir();

        yield 'none' => [null, $missing];
        yield 'no array' => ['App', $missing];
        yield 'no project root' => [['namespace' => 'App'], $missing];
        yield 'no namespace' => [['projectRoot' => $root], $missing];
        yield 'a list' => [[$root, 'App'], $missing];
        yield 'an unknown argument' => [
            ['projectRoot' => $root, 'namespace' => 'App', 'namespaces' => ['App']],
            'The configuration\'s beanAccessGenerator has the unknown argument namespaces.',
        ];

        $wrongTypes = [
            'projectRoot' => 1,
            'namespace' => ['App'],
            'sourceDirectory' => null,
            'accessNamespace' => false,
            'entityNamespace' => 1,
            'handWritten' => 'Request/',
            'lineLength' => '120',
        ];

        foreach ($wrongTypes as $name => $value) {
            yield 'a wrong ' . $name => [
                [...['projectRoot' => $root, 'namespace' => 'App'], $name => $value],
                'The configuration\'s beanAccessGenerator.' . $name . ' has the wrong type.',
            ];
        }

        yield 'a hand-written path that is no string' => [
            ['projectRoot' => $root, 'namespace' => 'App', 'handWritten' => ['Request/', 1]],
            'The configuration\'s beanAccessGenerator.handWritten has the wrong type.',
        ];
        yield 'hand-written paths that are no list' => [
            ['projectRoot' => $root, 'namespace' => 'App', 'handWritten' => ['a' => 'Request/']],
            'The configuration\'s beanAccessGenerator.handWritten has the wrong type.',
        ];
    }

    public function testTheTraitsAreWritten(): void
    {
        $request = $this->generate(null);

        self::assertSame(
            'new     src/BeanAccess/Doctrine/Repository/Archive/Deep/NoteRepoAccess.php' . PHP_EOL
            . 'new     src/BeanAccess/Doctrine/Repository/UserRepoAccess.php' . PHP_EOL
            . 'new     src/BeanAccess/Service/MailServiceAccess.php' . PHP_EOL
            . '3 traits, 3 written' . PHP_EOL,
            $this->printed($request),
        );
        self::assertSame(0, $request->getExitCode());
        self::assertFileExists($this->directory->getPath() . '/src/BeanAccess/Service/MailServiceAccess.php');
    }

    public function testATraitThatIsUpToDateIsLeftAlone(): void
    {
        $this->generate(null);
        file_put_contents(
            $this->directory->getPath() . '/src/BeanAccess/Doctrine/Repository/UserRepoAccess.php',
            'edited',
        );

        $request = $this->generate(null);

        self::assertSame(
            'changed src/BeanAccess/Doctrine/Repository/UserRepoAccess.php' . PHP_EOL . '3 traits, 1 written' . PHP_EOL,
            $this->printed($request),
        );
        self::assertSame('3 traits, 0 written' . PHP_EOL, $this->printed($this->generate(null)));
    }

    public function testACheckWritesNothingAndFailsWhenATraitWouldChange(): void
    {
        $request = $this->generate('check');

        self::assertSame(
            'new     src/BeanAccess/Doctrine/Repository/Archive/Deep/NoteRepoAccess.php' . PHP_EOL
            . 'new     src/BeanAccess/Doctrine/Repository/UserRepoAccess.php' . PHP_EOL
            . 'new     src/BeanAccess/Service/MailServiceAccess.php' . PHP_EOL
            . '3 traits, 3 would change' . PHP_EOL,
            $this->printed($request),
        );
        self::assertSame(1, $request->getExitCode());
        self::assertDirectoryDoesNotExist($this->directory->getPath() . '/src/BeanAccess');
    }

    public function testACheckOfTraitsThatAreUpToDatePasses(): void
    {
        $this->generate(null);

        $request = $this->generate('check');

        self::assertSame('3 traits, 0 would change' . PHP_EOL, $this->printed($request));
        self::assertSame(0, $request->getExitCode());
    }

    public function testAStaleTraitIsListedAndFailsTheCheck(): void
    {
        $this->generate(null);
        file_put_contents($this->directory->getPath() . '/src/BeanAccess/OldAccess.php', '<?php' . PHP_EOL);
        $stale = 'stale   src/BeanAccess/OldAccess.php (not generated any more; delete it by hand)' . PHP_EOL;

        $request = $this->generate(null);
        self::assertSame($stale . '3 traits, 0 written' . PHP_EOL, $this->printed($request));
        self::assertSame(0, $request->getExitCode());

        $request = $this->generate('check');
        self::assertSame($stale . '3 traits, 0 would change' . PHP_EOL, $this->printed($request));
        self::assertSame(1, $request->getExitCode());
    }

    public function testTheOptionalArgumentsAreTheGenerators(): void
    {
        $request = $this->generate(null, [
            'projectRoot' => $this->directory->getPath(),
            'namespace' => self::NAMESPACE,
            'sourceDirectory' => 'src',
            'accessNamespace' => 'Access',
            'entityNamespace' => null,
            'handWritten' => ['Request/'],
            'lineLength' => 100,
        ]);

        self::assertSame(
            'new     src/Access/Service/MailServiceAccess.php' . PHP_EOL . '1 traits, 1 written' . PHP_EOL,
            $this->printed($request),
        );
    }

    public function testAnArgumentOtherThanCheckIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The generator takes the argument check, or none; not force.');

        $this->generate('force');
    }

    /**
     * @param array<mixed>|string|null $arguments
     */
    #[DataProvider('provideArgumentsThatDoNotFit')]
    public function testArgumentsThatDoNotFitTheGeneratorAreRefused(null|array|string $arguments, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->generate(null, $arguments);
    }

    public function testBeansThatAreNoArrayAreRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configuration\'s beans must be an array, not string.');

        $this->generate(null, beans: 'MailService');
    }

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory('ampf-generator-controller');
        $this->directory->copy(__DIR__ . '/../../../Fixtures/GeneratorApp', 'src');
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    /**
     * Runs the controller on the copy of the fixture application, whose configuration names the mail service.
     *
     * @param array<mixed>|string|null $arguments the `beanAccessGenerator` block; by default the application's
     */
    private function generate(?string $mode, null|array|string $arguments = [], mixed $beans = null): CliRequest
    {
        $request = new CliRequest(['bin/index.php', 'beanAccess/generate']);
        $controller = new BeanAccessGeneratorController();
        $controller->setBeanFactory(new BeanFactory([
            'beans' => $beans ?? [MailServiceInterface::class => ['class' => 'a class']],
            'beanAccessGenerator' => $arguments === []
                ? ['projectRoot' => $this->directory->getPath(), 'namespace' => self::NAMESPACE]
                : $arguments,
        ]));
        $controller->setRequest($request);

        $controller->execute($mode);

        return $request;
    }

    private function printed(CliRequest $request): string
    {
        ob_start();
        $request->flush();

        return (string)ob_get_clean();
    }
}
