<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\BeanAccess;

use ampf\Bean\BeanFactory;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\Doctrine\DoctrineConfigAccess;
use ampf\BeanAccess\Doctrine\DoctrineEntityManagerAccess;
use ampf\BeanAccess\Doctrine\Repository\AbstractRepoAccess;
use ampf\BeanAccess\RouteResolverAccess;
use ampf\BeanAccess\Service\ConfigurationServiceAccess;
use ampf\BeanAccess\Service\HasherServiceAccess;
use ampf\BeanAccess\Service\SessionServiceAccess;
use ampf\BeanAccess\Service\StringCacheServiceAccess;
use ampf\BeanAccess\Service\TimeL10nServiceAccess;
use ampf\BeanAccess\Service\TranslatorServiceAccess;
use ampf\BeanAccess\Service\XsrfTokenServiceAccess;
use ampf\BeanAccess\ViewResolverAccess;
use ampf\Doctrine\DoctrineConfigInterface;
use ampf\Doctrine\EntityManagerFactoryInterface;
use ampf\Router\RouteResolverInterface;
use ampf\Service\Configuration\ConfigurationServiceInterface;
use ampf\Service\Hasher\HasherServiceInterface;
use ampf\Service\Session\SessionServiceInterface;
use ampf\Service\StringCache\StringCacheServiceInterface;
use ampf\Service\TimeL10n\TimeL10nServiceInterface;
use ampf\Service\Translator\TranslatorServiceInterface;
use ampf\Service\XsrfToken\XsrfTokenServiceInterface;
use ampf\Tests\Support\AccessTraitHost;
use ampf\Tests\Support\Doctrine\SampleEntity;
use ampf\Tests\Support\Doctrine\SampleRepo;
use ampf\View\ViewResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The access traits: each fetches its bean from the bean factory by the bean's interface, once, and takes a bean
 * handed in through its setter without a factory at all.
 */
#[CoversTrait(AbstractRepoAccess::class)]
#[CoversTrait(BeanFactoryAccess::class)]
#[CoversTrait(ConfigurationServiceAccess::class)]
#[CoversTrait(DoctrineConfigAccess::class)]
#[CoversTrait(DoctrineEntityManagerAccess::class)]
#[CoversTrait(HasherServiceAccess::class)]
#[CoversTrait(RouteResolverAccess::class)]
#[CoversTrait(SessionServiceAccess::class)]
#[CoversTrait(StringCacheServiceAccess::class)]
#[CoversTrait(TimeL10nServiceAccess::class)]
#[CoversTrait(TranslatorServiceAccess::class)]
#[CoversTrait(ViewResolverAccess::class)]
#[CoversTrait(XsrfTokenServiceAccess::class)]
final class AccessTraitsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, class-string}>
     */
    public static function provideAccessors(): iterable
    {
        yield 'ConfigurationServiceAccess' => [
            'getConfigurationService',
            'setConfigurationService',
            ConfigurationServiceInterface::class,
        ];
        yield 'DoctrineConfigAccess' => ['getDoctrineConfig', 'setDoctrineConfig', DoctrineConfigInterface::class];
        yield 'HasherServiceAccess' => ['getHasherService', 'setHasherService', HasherServiceInterface::class];
        yield 'RouteResolverAccess' => ['getRouteResolver', 'setRouteResolver', RouteResolverInterface::class];
        yield 'SessionServiceAccess' => ['getSessionService', 'setSessionService', SessionServiceInterface::class];
        yield 'StringCacheServiceAccess' => [
            'getStringCacheService',
            'setStringCacheService',
            StringCacheServiceInterface::class,
        ];
        yield 'TimeL10nServiceAccess' => ['getTimeL10nService', 'setTimeL10nService', TimeL10nServiceInterface::class];
        yield 'TranslatorServiceAccess' => [
            'getTranslatorService',
            'setTranslatorService',
            TranslatorServiceInterface::class,
        ];
        yield 'ViewResolverAccess' => ['getViewResolver', 'setViewResolver', ViewResolverInterface::class];
        yield 'XsrfTokenServiceAccess' => ['getXsrfTokenService', 'setXsrfTokenService', XsrfTokenServiceInterface::class];
    }

    /**
     * @param class-string $interface
     */
    #[DataProvider('provideAccessors')]
    public function testTheBeanIsFetchedByItsInterfaceOnce(string $getter, string $setter, string $interface): void
    {
        $bean = self::createStub($interface);
        $factory = new BeanFactory([]);
        $factory->set($interface, $bean);
        $host = new AccessTraitHost();
        $host->setBeanFactory($factory);

        self::assertSame($bean, $host->{$getter}());

        $factory->set($interface, self::createStub($interface));
        self::assertSame($bean, $host->{$getter}(), 'kept after the first call');

        $replacement = self::createStub($interface);
        $host->{$setter}($replacement);
        self::assertSame($replacement, $host->{$getter}(), 'the setter puts another bean in place');
    }

    /**
     * @param class-string $interface
     */
    #[DataProvider('provideAccessors')]
    public function testASetBeanNeedsNoFactory(string $getter, string $setter, string $interface): void
    {
        $bean = self::createStub($interface);
        $host = new AccessTraitHost();
        $host->{$setter}($bean);

        self::assertSame($bean, $host->{$getter}());
    }

    public function testTheEntityManagerComesFromTheFactoryBean(): void
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $factory = new BeanFactory([]);
        $factory->set(EntityManagerFactoryInterface::class, $this->entityManagerFactory($entityManager));
        $host = new AccessTraitHost();
        $host->setBeanFactory($factory);

        self::assertSame($entityManager, $host->getDoctrineEntityManager());
        self::assertSame($factory, $host->getBeanFactory());

        $other = new AccessTraitHost();
        $other->setDoctrineEntityManager($entityManager);
        self::assertSame($entityManager, $other->getDoctrineEntityManager(), 'set without a factory');
    }

    public function testARepositoryIsRegisteredAsABeanOfItsEntityOnFirstUse(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = new SampleRepo($entityManager, new ClassMetadata(SampleEntity::class));
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(SampleEntity::class)
            ->willReturn($repository)
        ;
        $factory = new BeanFactory([]);
        $factory->set(EntityManagerFactoryInterface::class, $this->entityManagerFactory($entityManager));
        $host = new AccessTraitHost();
        $host->setBeanFactory($factory);

        self::assertSame($repository, $host->repository(SampleEntity::class, SampleRepo::class));
        self::assertSame($repository, $host->repository(SampleEntity::class, SampleRepo::class), 'asked once');
        self::assertSame($repository, $factory->get('Doctrine.Repository.' . SampleEntity::class));
    }

    private function entityManagerFactory(EntityManagerInterface $entityManager): EntityManagerFactoryInterface
    {
        $factory = self::createStub(EntityManagerFactoryInterface::class);
        $factory->method('get')->willReturn($entityManager);

        return $factory;
    }
}
