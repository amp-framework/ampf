<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\GeneratorApp\Doctrine\Entity;

use ampf\Doctrine\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

/** An entity with Doctrine's own repository. */
#[ORM\Entity]
class LogEntity extends AbstractEntity
{
    use Audited;

    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    private int $id = 0;

    public function getId(): int
    {
        return $this->id;
    }
}
