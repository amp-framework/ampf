<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\GeneratorApp\Doctrine\Entity;

use ampf\Doctrine\Entity\AbstractEntity;
use ampf\Tests\Fixtures\GeneratorApp\Doctrine\Repository\UserRepo;
use Doctrine\ORM\Mapping as ORM;

/** An entity with a repository of its own. */
#[ORM\Entity(repositoryClass: UserRepo::class)]
class UserEntity extends AbstractEntity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    private int $id = 0;

    public function getId(): int
    {
        return $this->id;
    }
}
