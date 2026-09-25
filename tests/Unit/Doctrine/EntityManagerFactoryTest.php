<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Doctrine;

use ampf\Bean\BeanFactory;
use ampf\Bootstrap\DoctrineConfiguration;
use ampf\Doctrine\DoctrineConfig;
use ampf\Doctrine\DoctrineConfigInterface;
use ampf\Doctrine\EntityManagerFactory;
use ampf\Doctrine\Type\UTCDateTimeType;
use ampf\Tests\Support\Doctrine\SampleEntity;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The entity manager of a bean factory: created once, at the first get() or by the bean's init(), from the
 * configuration's `doctrine` block — its DBAL type overrides, its connection, its platform's type mappings.
 */
#[CoversClass(DoctrineConfig::class)]
#[CoversClass(EntityManagerFactory::class)]
final class EntityManagerFactoryTest extends TestCase
{
    public function testTheEntityManagerIsCreatedOnceAtItsFirstUse(): void
    {
        $factory = $this->factory([]);
        $entityManager = $factory->get();

        self::assertSame($entityManager, $factory->get());

        $factory->init();
        self::assertSame($entityManager, $factory->get(), 'the initMethod keeps it as well');
    }

    public function testInitCreatesItForTheFirstGet(): void
    {
        $factory = $this->factory([]);
        $factory->init();

        self::assertSame($factory->get(), $factory->get());
    }

    public function testTheDoctrineBlockConfiguresTheEntityManager(): void
    {
        $entityManager = $this->factory(['enum' => 'string'])->get();

        self::assertInstanceOf(UTCDateTimeType::class, Type::getType('datetime'));
        self::assertSame(
            'string',
            $entityManager->getConnection()->getDatabasePlatform()->getDoctrineTypeMapping('enum'),
        );
        self::assertSame('sample', $entityManager->getClassMetadata(SampleEntity::class)->getTableName());
    }

    public function testAFactoryMayCreateTheEntityManagerItsOwnWay(): void
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $factory = new class extends EntityManagerFactory {
            private ?EntityManagerInterface $prepared = null;

            public function prepare(EntityManagerInterface $entityManager): void
            {
                $this->prepared = $entityManager;
            }

            protected function createEntityManager(): EntityManagerInterface
            {
                return $this->prepared ?? parent::createEntityManager();
            }
        };
        $factory->prepare($entityManager);

        self::assertSame($entityManager, $factory->get());
    }

    /**
     * @param array<string, string> $mappingOverrides
     */
    private function factory(array $mappingOverrides): EntityManagerFactory
    {
        $factory = new EntityManagerFactory();
        $factory->setBeanFactory(new BeanFactory([
            'beans' => [DoctrineConfigInterface::class => ['class' => DoctrineConfig::class]],
            'doctrine' => [
                'configuration' => DoctrineConfiguration::create([dirname(__DIR__, 2) . '/Support/Doctrine']),
                'connectionParams' => ['driver' => 'pdo_sqlite', 'memory' => true],
                'typeOverrides' => ['datetime' => UTCDateTimeType::class],
                'mappingOverrides' => $mappingOverrides,
            ],
        ]));

        return $factory;
    }
}
