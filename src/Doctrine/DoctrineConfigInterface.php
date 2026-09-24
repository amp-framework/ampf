<?php

declare(strict_types=1);

namespace ampf\Doctrine;

use Doctrine\ORM\Configuration;

/**
 * What the entity manager factory needs to create the entity manager.
 *
 * @phpstan-import-type Params from \Doctrine\DBAL\DriverManager
 */
interface DoctrineConfigInterface
{
    public function getConfiguration(): Configuration;

    /**
     * The parameters of the connection (DriverManager::getConnection()).
     *
     * @return Params
     */
    public function getConnectionParams(): array;

    /**
     * @return array<string, mixed>
     */
    public function getMappingOverrides(): array;

    /**
     * @return array<string, mixed>
     */
    public function getTypeOverrides(): array;
}
