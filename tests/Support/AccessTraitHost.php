<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\Doctrine\DoctrineConfigAccess;
use ampf\BeanAccess\Doctrine\DoctrineEntityManagerAccess;
use ampf\BeanAccess\Doctrine\EntityManagerFactoryAccess;
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
use ampf\Doctrine\Repository\AbstractRepo;

/**
 * A bean with every access trait the framework ships, as an application's bean has some of them.
 */
final class AccessTraitHost implements BeanFactoryAccessInterface
{
    use AbstractRepoAccess;
    use BeanFactoryAccess;
    use ConfigurationServiceAccess;
    use DoctrineConfigAccess;
    use DoctrineEntityManagerAccess;
    use EntityManagerFactoryAccess;
    use HasherServiceAccess;
    use RouteResolverAccess;
    use SessionServiceAccess;
    use StringCacheServiceAccess;
    use TimeL10nServiceAccess;
    use TranslatorServiceAccess;
    use ViewResolverAccess;
    use XsrfTokenServiceAccess;

    /**
     * What a repository's own access trait asks for.
     *
     * @template T of \ampf\Doctrine\Entity\AbstractEntity
     * @template U of \ampf\Doctrine\Repository\AbstractRepo<T>
     *
     * @param class-string<T> $entityClass
     * @param class-string<U> $repoClass
     *
     * @return U
     */
    public function repository(string $entityClass, string $repoClass): AbstractRepo
    {
        return $this->getDoctrineEntityRepository($entityClass, $repoClass);
    }
}
