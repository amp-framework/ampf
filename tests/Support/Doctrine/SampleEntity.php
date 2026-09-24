<?php

declare(strict_types=1);

namespace ampf\Tests\Support\Doctrine;

use ampf\Doctrine\Entity\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity of the tests' own, mapped by attributes like an application's.
 */
#[ORM\Entity(repositoryClass: SampleRepo::class)]
#[ORM\Table(name: 'sample')]
class SampleEntity extends AbstractEntity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    #[ORM\Id]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
