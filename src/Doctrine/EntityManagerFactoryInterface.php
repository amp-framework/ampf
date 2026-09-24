<?php

declare(strict_types=1);

namespace ampf\Doctrine;

use Doctrine\ORM\EntityManagerInterface;

/** The one entity manager of a bean factory (a request, a CLI run). */
interface EntityManagerFactoryInterface
{
    /** The entity manager, created at the first call unless init() created it already. */
    public function get(): EntityManagerInterface;
}
