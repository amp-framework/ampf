<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\BeanAccess\Generator;

use ampf\BeanAccess\Generator\BeanAccessGenerator;
use ampf\Service\Hasher\HasherServiceInterface;
use ampf\Tests\Fixtures\GeneratorApp\Doctrine\Entity\Archive\Deep\NoteEntity;
use ampf\Tests\Fixtures\GeneratorApp\Doctrine\Entity\UserEntity;
use ampf\Tests\Fixtures\GeneratorApp\Doctrine\Repository\Archive\Deep\NoteRepo;
use ampf\Tests\Fixtures\GeneratorApp\Doctrine\Repository\UserRepo;
use ampf\Tests\Fixtures\GeneratorApp\GreeterInterface;
use ampf\Tests\Fixtures\GeneratorApp\Service\ClockInterface;
use ampf\Tests\Fixtures\GeneratorApp\Service\Mail\MailQueueInterface;
use ampf\Tests\Fixtures\GeneratorApp\Service\Mail\MailService;
use ampf\Tests\Fixtures\GeneratorApp\Service\Mail\MailServiceInterface;
use ampf\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(BeanAccessGenerator::class)]
final class BeanAccessGeneratorTest extends TestCase
{
    private const string NAMESPACE = 'ampf\Tests\Fixtures\GeneratorApp';

    private const string FIXTURE = __DIR__ . '/../../../Fixtures/GeneratorApp';

    private ?TemporaryDirectory $directory = null;

    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function providePaths(): iterable
    {
        yield 'a service in a namespace of its own' => [
            MailServiceInterface::class,
            'MailServiceAccess',
            'src/BeanAccess/Service/MailServiceAccess.php',
        ];
        yield 'a service one level deep' => [ClockInterface::class, 'ClockAccess', 'src/BeanAccess/ClockAccess.php'];
        yield 'a service without a namespace' => [
            GreeterInterface::class,
            'GreeterAccess',
            'src/BeanAccess/GreeterAccess.php',
        ];
        yield 'a repository' => [
            UserRepo::class,
            'UserRepoAccess',
            'src/BeanAccess/Doctrine/Repository/UserRepoAccess.php',
        ];
        yield 'a repository deep down' => [
            NoteRepo::class,
            'NoteRepoAccess',
            'src/BeanAccess/Doctrine/Repository/Archive/Deep/NoteRepoAccess.php',
        ];
        yield 'a class' => [MailService::class, 'MailServiceAccess', 'src/BeanAccess/Service/MailServiceAccess.php'];
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideLineLengths(): iterable
    {
        $call = '            $this->setUserRepo($this->getDoctrineEntityRepository(UserEntity::class, UserRepo::class));';
        $wrappedOnce = "            \$this->setUserRepo(\n"
            . "                \$this->getDoctrineEntityRepository(UserEntity::class, UserRepo::class),\n"
            . '            );';
        $wrappedTwice = "            \$this->setUserRepo(\n"
            . "                \$this->getDoctrineEntityRepository(\n"
            . "                    UserEntity::class,\n"
            . "                    UserRepo::class,\n"
            . "                ),\n"
            . '            );';
        $signature = "    public function setUserRepo(UserRepo \$object): void\n    {";
        $wrappedSignature = "    public function setUserRepo(\n        UserRepo \$object,\n    ): void {";
        $assert = '        assert($this->__userRepo instanceof UserRepo);';
        $wrappedAssert = "        assert(\n            \$this->__userRepo instanceof UserRepo,\n        );";

        yield 'the call fits' => [103, $call];
        yield 'the call is one too long' => [102, $wrappedOnce];
        yield 'the inner call fits' => [87, $wrappedOnce];
        yield 'the inner call is one too long' => [86, $wrappedTwice];
        yield 'the signature fits' => [55, $signature];
        yield 'the signature is one too long' => [54, $wrappedSignature];
        yield 'the assertion fits' => [54, $assert];
        yield 'the assertion is one too long' => [53, $wrappedAssert];
    }

    public function testTheFrameworksOwnTraitsAreExactlyWhatTheGeneratorProduces(): void
    {
        $config = require __DIR__ . '/../../../../config/default.php';
        self::assertIsArray($config);
        self::assertIsArray($config['beans']);
        $generator = new BeanAccessGenerator(
            dirname(__DIR__, 4),
            'ampf',
            handWritten: ['BeanFactoryAccess.php', 'Doctrine/DoctrineEntityManagerAccess.php'],
        );

        $files = $generator->generate($config['beans']);

        self::assertCount(11, $files);
        self::assertSame([], $generator->changedFiles($files), 'generate them anew');
        self::assertSame([], $generator->staleFiles($files), 'traits nothing generates any more');
    }

    public function testEverySingletonBeanKeyedByAnInterfaceOfTheApplicationHasATrait(): void
    {
        self::assertSame(
            [MailServiceInterface::class, GreeterInterface::class, ClockInterface::class],
            $this->generator()->interfaceBeans([
                'Request' => ['class' => 'a class'],
                MailServiceInterface::class => ['class' => MailService::class],
                MailQueueInterface::class => ['class' => 'a class', 'scope' => 'prototype'],
                GreeterInterface::class => ['class' => 'a class', 'scope' => 'singleton'],
                MailService::class => ['class' => MailService::class],
                HasherServiceInterface::class => ['class' => 'a class'],
                self::NAMESPACE . '\Missing\MissingInterface' => ['class' => 'a class'],
                ClockInterface::class => ['class' => 'a class'],
                self::NAMESPACE . 'Other\OtherInterface' => ['class' => 'a class'],
                0 => ['class' => 'a class'],
                'ampf\Tests\Fixtures\GeneratorApp\Service\ClockInterface ' => 'no definition',
            ]),
        );
    }

    public function testEveryEntityThatNamesARepositoryHasATraitWhereverItIs(): void
    {
        self::assertSame(
            [NoteEntity::class => NoteRepo::class, UserEntity::class => UserRepo::class],
            $this->generator()->repositories(),
        );
    }

    public function testWithoutEntitiesThereAreNoRepositories(): void
    {
        self::assertSame([], $this->generator(entityNamespace: null)->repositories());
        self::assertSame([], $this->generator(entityNamespace: 'Missing\Entity')->repositories());
    }

    public function testAnEntityFileWithoutItsTypeIsRefused(): void
    {
        $root = $this->copy();
        file_put_contents($root . '/src/Doctrine/Entity/GhostEntity.php', "<?php\n");
        file_put_contents($root . '/src/Doctrine/Entity/notes.txt', 'no PHP');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The file ' . $root . '/src/Doctrine/Entity/GhostEntity.php does not declare '
            . self::NAMESPACE . '\Doctrine\Entity\GhostEntity.',
        );

        $this->generator($root)->repositories();
    }

    /**
     * @param class-string $type
     */
    #[DataProvider('providePaths')]
    public function testTheTraitsNameAndPathAreDerivedFromTheType(string $type, string $trait, string $path): void
    {
        self::assertSame($trait, $this->generator()->traitName($type));
        self::assertSame($path, $this->generator()->pathFor($type));
    }

    public function testTheTraitsGoWhereTheConfigurationSays(): void
    {
        $generator = new BeanAccessGenerator(
            dirname(__DIR__, 4),
            self::NAMESPACE,
            'tests/Fixtures/GeneratorApp',
            'Generated\Access',
        );

        self::assertSame(
            'tests/Fixtures/GeneratorApp/Generated/Access/Service/MailServiceAccess.php',
            $generator->pathFor(MailServiceInterface::class),
        );
        self::assertStringContainsString(
            "\nnamespace ampf\\Tests\\Fixtures\\GeneratorApp\\Generated\\Access\\Service;\n",
            $generator->generate([MailServiceInterface::class => []])[$generator->pathFor(MailServiceInterface::class)],
        );
    }

    public function testATypeOutsideTheApplicationHasNoTrait(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The type ' . HasherServiceInterface::class . ' is not in the namespace ' . self::NAMESPACE . '.',
        );

        $this->generator()->pathFor(HasherServiceInterface::class);
    }

    public function testAServiceTraitFetchesItsBeanByItsInterface(): void
    {
        $files = $this->generator()->generate([MailServiceInterface::class => ['class' => MailService::class]]);

        self::assertSame(
            ['src/BeanAccess/Doctrine/Repository/Archive/Deep/NoteRepoAccess.php', 'src/BeanAccess/Doctrine/Repository/UserRepoAccess.php', 'src/BeanAccess/Service/MailServiceAccess.php'],
            array_keys($files),
        );
        self::assertSame(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace ampf\Tests\Fixtures\GeneratorApp\BeanAccess\Service;

                use ampf\BeanAccess\AbstractAccess;
                use ampf\Tests\Fixtures\GeneratorApp\Service\Mail\MailServiceInterface;

                trait MailServiceAccess
                {
                    use AbstractAccess;

                    protected ?MailServiceInterface $__mailService = null;

                    public function getMailService(): MailServiceInterface
                    {
                        if ($this->__mailService === null) {
                            $object = $this->getBeanFactory()->get(MailServiceInterface::class);
                            assert($object instanceof MailServiceInterface);
                            $this->setMailService($object);
                        }

                        assert($this->__mailService instanceof MailServiceInterface);

                        return $this->__mailService;
                    }

                    public function setMailService(MailServiceInterface $object): void
                    {
                        $this->__mailService = $object;
                    }
                }

                PHP,
            $files['src/BeanAccess/Service/MailServiceAccess.php'],
        );
    }

    public function testATraitInTheNamespaceOfTheAbstractAccessDoesNotImportIt(): void
    {
        $files = new BeanAccessGenerator(dirname(__DIR__, 4), 'ampf', entityNamespace: null)->generate([
            'ampf\Router\RouteResolverInterface' => [],
        ]);

        self::assertStringContainsString(
            "namespace ampf\\BeanAccess;\n\nuse ampf\\Router\\RouteResolverInterface;\n\ntrait RouteResolverAccess\n",
            $files['src/BeanAccess/RouteResolverAccess.php'],
        );
    }

    public function testARepositoryTraitFetchesItsRepositoryThroughTheEntity(): void
    {
        self::assertSame(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace ampf\Tests\Fixtures\GeneratorApp\BeanAccess\Doctrine\Repository;

                use ampf\BeanAccess\Doctrine\Repository\AbstractRepoAccess;
                use ampf\Tests\Fixtures\GeneratorApp\Doctrine\Entity\UserEntity;
                use ampf\Tests\Fixtures\GeneratorApp\Doctrine\Repository\UserRepo;

                trait UserRepoAccess
                {
                    use AbstractRepoAccess;

                    protected ?UserRepo $__userRepo = null;

                    public function getUserRepo(): UserRepo
                    {
                        if ($this->__userRepo === null) {
                            $this->setUserRepo($this->getDoctrineEntityRepository(UserEntity::class, UserRepo::class));
                        }

                        assert($this->__userRepo instanceof UserRepo);

                        return $this->__userRepo;
                    }

                    public function setUserRepo(UserRepo $object): void
                    {
                        $this->__userRepo = $object;
                    }
                }

                PHP,
            $this->userRepoAccess(120),
        );
    }

    /**
     * The repository's longest lines: the setter call (103 characters), the inner call wrapped out of it (87), the
     * setter's signature (55) and the assertion (54).
     */
    #[DataProvider('provideLineLengths')]
    public function testLongLinesAreWrappedAsPhpcsWrapsThem(int $lineLength, string $expected): void
    {
        self::assertStringContainsString($expected, $this->userRepoAccess($lineLength));
    }

    public function testATraitNothingGeneratesAnyMoreIsStale(): void
    {
        $root = $this->copy();
        $generator = $this->generator($root, handWritten: ['Request/', 'HandAccess.php']);
        $files = $generator->generate([MailServiceInterface::class => []]);
        $generator->write($files);

        mkdir($root . '/src/BeanAccess/Request');
        $paths = [
            'Service/OldServiceAccess.php',
            'Request/FilterAccess.php',
            'HandAccess.php',
            'Service/HandAccess.php',
            'AbstractBaseAccess.php',
            'Helper.php',
            'OldAccess.php',
        ];

        foreach ($paths as $path) {
            file_put_contents($root . '/src/BeanAccess/' . $path, '<?php' . PHP_EOL);
        }

        self::assertSame(
            ['src/BeanAccess/OldAccess.php', 'src/BeanAccess/Service/HandAccess.php', 'src/BeanAccess/Service/OldServiceAccess.php'],
            $generator->staleFiles($files),
        );
    }

    public function testWithoutTraitsNoneIsStale(): void
    {
        self::assertSame([], $this->generator($this->copy())->staleFiles([]));
    }

    public function testWhatIsWrittenIsNotChangedAnyMore(): void
    {
        $root = $this->copy();
        $generator = $this->generator($root);
        $files = $generator->generate([MailServiceInterface::class => [], GreeterInterface::class => []]);

        self::assertSame(
            [
                'src/BeanAccess/Doctrine/Repository/Archive/Deep/NoteRepoAccess.php' => 'new',
                'src/BeanAccess/Doctrine/Repository/UserRepoAccess.php' => 'new',
                'src/BeanAccess/GreeterAccess.php' => 'new',
                'src/BeanAccess/Service/MailServiceAccess.php' => 'new',
            ],
            $generator->changedFiles($files),
        );

        $generator->write($files);

        self::assertSame([], $generator->changedFiles($files));
        self::assertStringEqualsFile(
            $root . '/src/BeanAccess/GreeterAccess.php',
            $files['src/BeanAccess/GreeterAccess.php'],
        );

        file_put_contents($root . '/src/BeanAccess/GreeterAccess.php', 'edited by hand', FILE_APPEND);
        self::assertSame(['src/BeanAccess/GreeterAccess.php' => 'changed'], $generator->changedFiles($files));
    }

    public function testAFileThatCannotBeWrittenIsReported(): void
    {
        $root = $this->copy();
        mkdir($root . '/src/BeanAccess/GreeterAccess.php', 0o755, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The trait src/BeanAccess/GreeterAccess.php cannot be written.');

        $this->generator($root)->write(['src/BeanAccess/GreeterAccess.php' => '<?php']);
    }

    public function testADirectoryThatCannotBeCreatedIsReported(): void
    {
        $root = $this->copy();
        file_put_contents($root . '/src/BeanAccess', 'a file where the directory goes');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The directory of the trait src/BeanAccess/Service/MailServiceAccess.php cannot be created.',
        );

        $this->generator($root)->write(['src/BeanAccess/Service/MailServiceAccess.php' => '<?php']);
    }

    public function testTheProjectRootMustBeADirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The project root ' . self::FIXTURE . '/missing is no directory.');

        new BeanAccessGenerator(self::FIXTURE . '/missing', self::NAMESPACE);
    }

    protected function tearDown(): void
    {
        $this->directory?->remove();
    }

    /**
     * A generator of the fixture application, in a copy of it unless the root is given.
     *
     * @param list<string> $handWritten
     */
    private function generator(
        ?string $root = null,
        ?string $entityNamespace = 'Doctrine\Entity',
        array $handWritten = [],
        int $lineLength = 120,
    ): BeanAccessGenerator {
        return new BeanAccessGenerator(
            $root ?? $this->copy(),
            self::NAMESPACE,
            entityNamespace: $entityNamespace,
            handWritten: $handWritten,
            lineLength: $lineLength,
        );
    }

    private function userRepoAccess(int $lineLength): string
    {
        $generator = $this->generator(lineLength: $lineLength);

        return $generator->generate([])['src/BeanAccess/Doctrine/Repository/UserRepoAccess.php'];
    }

    /** A copy of the fixture application as a project of its own, its classes in src/: one per test. */
    private function copy(): string
    {
        if ($this->directory === null) {
            $this->directory = new TemporaryDirectory('ampf-generator');
            $this->directory->copy(self::FIXTURE, 'src');
        }

        return $this->directory->getPath();
    }
}
