<?php

declare(strict_types=1);

namespace ampf\doctrine;

use Doctrine\ORM\Configuration;

interface Config
{
    public function getConfiguration(): Configuration;

    /**
     * @return array{
     *     'driver': 'pdo_mysql',
     *     'host': string,
     *     'user': string,
     *     'password': string,
     *     'dbname': string,
     *     'charset': string,
     *     'driverOptions': array<string, string>,
     * }
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
