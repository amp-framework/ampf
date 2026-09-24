<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Doctrine\Repository;

use ampf\Bootstrap\DoctrineConfiguration;
use ampf\Doctrine\Repository\AbstractRepo;
use ampf\Tests\Support\Doctrine\SampleEntity;
use ampf\Tests\Support\Doctrine\SampleRepo;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
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
        $this->expectExceptionMessage('Expected an instance of ' . SampleEntity::class);
        $this->repository->findNamesAsEntities();
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
