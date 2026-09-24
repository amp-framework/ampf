<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Doctrine\Repository;

use ampf\Bean\BeanFactoryInterface;
use ampf\BeanAccess\AbstractAccess;
use ampf\Doctrine\EntityManagerFactoryInterface;
use ampf\Doctrine\Repository\AbstractRepo;

/**
 * The base of a repository's access trait. Repository beans are registered on first use under
 * 'Doctrine.Repository.' . entity class: the entity manager creates the repository the entity's mapping names, the
 * bean factory keeps it for its scope. A trait for one repository looks like this:
 *
 *     trait UserRepoAccess
 *     {
 *         use AbstractRepoAccess;
 *
 *         protected ?UserRepo $__userRepo = null;
 *
 *         public function getUserRepo(): UserRepo
 *         {
 *             if ($this->__userRepo === null) {
 *                 $this->setUserRepo($this->getDoctrineEntityRepository(UserEntity::class, UserRepo::class));
 *             }
 *
 *             assert($this->__userRepo instanceof UserRepo);
 *
 *             return $this->__userRepo;
 *         }
 *
 *         public function setUserRepo(UserRepo $object): void
 *         {
 *             $this->__userRepo = $object;
 *         }
 *     }
 */
trait AbstractRepoAccess
{
    use AbstractAccess;

    /**
     * @template T of \ampf\Doctrine\Entity\AbstractEntity
     * @template U of \ampf\Doctrine\Repository\AbstractRepo<T>
     *
     * @param class-string<T> $entityClass
     * @param class-string<U> $repoClass
     *
     * @return U
     */
    protected function getDoctrineEntityRepository(string $entityClass, string $repoClass): AbstractRepo
    {
        $result = $this->getBeanFactory()->get(
            'Doctrine.Repository.' . $entityClass,
            static function (BeanFactoryInterface $beanFactory) use ($entityClass, $repoClass): AbstractRepo {
                $emFactory = $beanFactory->get(EntityManagerFactoryInterface::class);
                assert($emFactory instanceof EntityManagerFactoryInterface);
                $repo = $emFactory->get()->getRepository($entityClass);
                assert($repo instanceof $repoClass);

                return $repo;
            },
        );
        assert($result instanceof $repoClass);

        return $result;
    }
}
