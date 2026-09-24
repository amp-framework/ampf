<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\GeneratorApp\Doctrine\Entity\Archive\Deep;

use ampf\Doctrine\Entity\AbstractEntity;
use ampf\Tests\Fixtures\GeneratorApp\Doctrine\Repository\Archive\Deep\NoteRepo;
use Doctrine\ORM\Mapping as ORM;

/** An entity two namespace levels below the entities'. */
#[ORM\Entity(repositoryClass: NoteRepo::class)]
class NoteEntity extends AbstractEntity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    private int $id = 0;

    public function getId(): int
    {
        return $this->id;
    }
}
