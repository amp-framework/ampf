<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Doctrine;

use ampf\Bean\BeanFactoryAccessInterface;
use ampf\BeanAccess\BeanFactoryAccess;
use ampf\BeanAccess\Doctrine\Repository\AbstractRepoAccess;

/**
 * A base class with the repositories' access, as an application's base service has it: its subclasses reach any
 * entity's repository.
 */
abstract class RepositoryAccessHost implements BeanFactoryAccessInterface
{
    use AbstractRepoAccess;
    use BeanFactoryAccess;

    abstract public function samples(): SampleRepo;
}
