<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Doctrine;

use ampf\Doctrine\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with Doctrine's own repository, which is no AbstractRepo.
 */
#[ORM\Entity]
#[ORM\Table(name: 'plain')]
class PlainEntity extends AbstractEntity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
