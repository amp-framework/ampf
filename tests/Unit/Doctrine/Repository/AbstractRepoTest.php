<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Doctrine\Repository;

use ampf\Bootstrap\DoctrineConfiguration;
use ampf\Doctrine\Repository\AbstractRepo;
use ampf\Tests\Support\Doctrine\PlainEntity;
use ampf\Tests\Support\Doctrine\SampleEntity;
use ampf\Tests\Support\Doctrine\SampleRepo;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A repository's typed readers over a real entity manager on SQLite in memory: the rows of a query as the
 * repository's entities, one entity or null, a scalar or the number of rows a DQL UPDATE changed as an int — and a
 * refusal for rows of another shape.
 */
#[CoversClass(AbstractRepo::class)]
final class AbstractRepoTest extends TestCase
{
    private EntityManager $entityManager;

    private SampleRepo $repository;

    public function testTheReadersReturnTheRepositorysEntities(): void
    {
        $this->store('alpha', 'beta', 'alpha');

        self::assertSame(3, $this->repository->findAllCount());
        self::assertSame(2, $this->repository->countNamed('alpha'));
        self::assertSame(
            ['alpha', 'alpha'],
            array_map(
                static fn (SampleEntity $entity): string => $entity->getName(),
                $this->repository->findNamed('alpha'),
            ),
        );
        self::assertSame('beta', $this->repository->findOneNamed('beta')?->getName());
        self::assertNull($this->repository->findOneNamed('gamma'));
        self::assertSame([], $this->repository->findNamed('gamma'));
    }

    public function testAnUpdateTellsHowManyRowsItChanged(): void
    {
        $this->store('alpha', 'beta', 'alpha');

        self::assertSame(2, $this->repository->rename('alpha', 'omega'));
        self::assertSame(0, $this->repository->rename('alpha', 'omega'));
        self::assertSame(2, $this->repository->countNamed('omega'));
    }

    public function testRowsThatAreNoEntitiesAreRefused(): void
    {
        $this->store('alpha');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The query\'s result holds something other than a ' . SampleEntity::class . ': array.',
        );
        $this->repository->findNamesAsEntities();
    }

    public function testTheEntitiesThatMatchTheCriteriaAreRemoved(): void
    {
        $this->store(...array_fill(0, 45, 'alpha'));
        $this->store('beta', 'gamma');

        $beta = $this->repository->findOneNamed('beta')?->getId();

        self::assertSame(
            45,
            $this->repository->bulkRemoveBy(['name' => 'alpha']),
            'flushed after every 20 and at the end',
        );
        self::assertSame(0, $this->repository->bulkRemoveBy(['name' => null]), 'null matches NULL');
        self::assertSame(0, $this->repository->bulkRemoveBy(['name' => 'gamma', 'id' => $beta]), 'every criterion');
        self::assertSame(1, $this->repository->bulkRemoveBy(['name' => 'beta', 'id' => $beta]));
        self::assertSame(1, $this->repository->findAllCount());
        self::assertSame('gamma', $this->repository->findOneNamed('gamma')?->getName());
    }

    public function testARemovalWithoutCriteriaIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A removal without criteria would empty the whole table: use a query for that.');

        $this->repository->bulkRemoveBy([]);
    }

    public function testACriterionNeedsAField(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A criterion needs the name of a field.');

        $this->repository->bulkRemoveBy(['name' => 'alpha', ' ' => 'beta']);
    }

    public function testRowsThatAreNoEntitiesAreNotRemoved(): void
    {
        $this->store('alpha');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The query did not select entities.');

        $this->repository->removeNames();
    }

    public function testACreatedEntityIsPersistedButNotFlushed(): void
    {
        $entity = $this->repository->create();

        self::assertTrue($this->entityManager->contains($entity));
        self::assertSame(0, $this->repository->findAllCount());
    }

    public function testAnEntityOfTheRepositorysClassIsOne(): void
    {
        self::assertTrue($this->repository->is(new SampleEntity()));
        self::assertFalse($this->repository->is(new PlainEntity()));
        self::assertFalse($this->repository->is(null));
    }

    public function testARowThatIsNoEntityIsRefusedAsTheOne(): void
    {
        $this->store('alpha');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The query\'s result is no ' . SampleEntity::class . ', but array.');

        $this->repository->findOneNameAsEntity();
    }

    public function testAResultThatIsNoNumberIsRefused(): void
    {
        $this->store('alpha');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The query\'s result is no number, but string.');

        $this->repository->countNames();
    }

    public function testASelectChangesNoRows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The query\'s result is no number of rows, but array.');

        $this->repository->renameBySelecting();
    }

    public function testAnotherEntitysRepositoryIsAnAbstractRepo(): void
    {
        self::assertSame($this->repository, $this->repository->repositoryOf(SampleEntity::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The repository of ' . PlainEntity::class . ' is no ' . AbstractRepo::class . ', but '
            . EntityRepository::class . '.',
        );

        $this->repository->repositoryOf(PlainEntity::class);
    }

    protected function setUp(): void
    {
        $this->entityManager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            DoctrineConfiguration::create([dirname(__DIR__, 3) . '/Support/Doctrine']),
        );
        new SchemaTool($this->entityManager)->createSchema(
            [$this->entityManager->getClassMetadata(SampleEntity::class)],
        );

        $this->repository = $this->entityManager->getRepository(SampleEntity::class);
    }

    private function store(string ...$names): void
    {
        foreach ($names as $name) {
            $this->repository->create()->setName($name);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
