<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Doctrine;

use ampf\Bean\BeanFactory;
use ampf\Bootstrap\DoctrineConfiguration;
use ampf\Doctrine\DoctrineConfig;
use Doctrine\ORM\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DoctrineConfig::class)]
final class DoctrineConfigTest extends TestCase
{
    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function provideBlocksThatAreRefused(): iterable
    {
        yield 'no block' => [[], 'The configuration has no doctrine block.'];
        yield 'a block that is no array' => [['doctrine' => 'mysql'], 'The configuration has no doctrine block.'];
        yield 'an empty block' => [['doctrine' => []], 'The configuration has no doctrine block.'];
        yield 'a list' => [
            ['doctrine' => ['mysql']],
            'The configuration\'s doctrine block must be keyed by names, not 0.',
        ];
    }

    public function testTheBlockOfTheBeanConfigIsReadOnce(): void
    {
        $configuration = DoctrineConfiguration::create([]);
        $beanFactory = new BeanFactory(['doctrine' => $this->block($configuration)]);
        $config = new DoctrineConfig();
        $config->setBeanFactory($beanFactory);

        self::assertSame($configuration, $config->getConfiguration());
        self::assertSame(['driver' => 'pdo_sqlite', 'memory' => true], $config->getConnectionParams());
        self::assertSame(['datetime' => 'a type'], $config->getTypeOverrides());
        self::assertSame(['enum' => 'string'], $config->getMappingOverrides());

        $beanFactory->set('Config', ['doctrine' => ['other' => true]]);
        self::assertSame($this->block($configuration), $config->getConfig(), 'read once');
    }

    public function testAGivenBlockNeedsNoBeanFactory(): void
    {
        $config = new DoctrineConfig();
        $config->setConfig(['doctrine' => ['typeOverrides' => []]]);

        self::assertSame(['typeOverrides' => []], $config->getConfig());
        self::assertSame([], $config->getTypeOverrides());
    }

    /**
     * @param array<mixed> $config
     */
    #[DataProvider('provideBlocksThatAreRefused')]
    public function testABlockThatIsMissingEmptyOrNotKeyedByNamesIsRefused(array $config, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        // @phpstan-ignore argument.type (what is no block, on purpose)
        new DoctrineConfig()->setConfig($config);
    }

    public function testAConfigMayReadTheBlockItsOwnWay(): void
    {
        $config = new class extends DoctrineConfig {
            /**
             * @var list<string>
             */
            private array $calls = [];

            /**
             * @return list<string>
             */
            public function getCalls(): array
            {
                return $this->calls;
            }

            /**
             * @param array<mixed> $config
             *
             * @return array<string, mixed>
             */

            protected function blockOf(array $config): array
            {
                $this->calls[] = __FUNCTION__;

                return parent::blockOf($config) + ['typeOverrides' => []];
            }

            /**
             * @return array<string, mixed>
             */

            protected function getArrayValue(string $key): array
            {
                $this->calls[] = __FUNCTION__ . ' ' . $key;

                return parent::getArrayValue($key);
            }

            protected function getConfigValue(string $value): mixed
            {
                $this->calls[] = __FUNCTION__ . ' ' . $value;

                return $value === 'mappingOverrides'
                    ? ['enum' => 'string']
                    : parent::getConfigValue($value);
            }
        };
        $config->setConfig(['doctrine' => ['connectionParams' => ['driver' => 'pdo_sqlite']]]);

        self::assertSame([], $config->getTypeOverrides(), 'what the block lacks, the subclass adds');
        self::assertSame(['enum' => 'string'], $config->getMappingOverrides());
        self::assertSame(
            [
                'blockOf',
                'getArrayValue typeOverrides',
                'getConfigValue typeOverrides',
                'getArrayValue mappingOverrides',
                'getConfigValue mappingOverrides',
            ],
            $config->getCalls(),
        );
    }

    public function testAConfigurationThatIsNoOrmConfigurationIsRefused(): void
    {
        $config = new DoctrineConfig();
        $config->setConfig(['doctrine' => ['configuration' => 'orm.php']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configuration\'s doctrine.configuration must be a ' . Configuration::class
            . ' (DoctrineConfiguration::create()), not string.',
        );

        $config->getConfiguration();
    }

    public function testValuesThatAreNoArraysAreRefused(): void
    {
        $config = new DoctrineConfig();
        $config->setConfig(['doctrine' => ['connectionParams' => 'mysql://', 'typeOverrides' => null]]);

        foreach (
            [
                'getConnectionParams' => 'The configuration\'s doctrine.connectionParams must be an array, not string.',
                'getTypeOverrides' => 'The configuration\'s doctrine.typeOverrides must be an array, not null.',
                'getMappingOverrides' => 'The configuration\'s doctrine.mappingOverrides must be an array, not null.',
            ] as $method => $message
        ) {
            try {
                $config->{$method}();
                self::fail($method . ' took what is no array');
            } catch (RuntimeException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function block(Configuration $configuration): array
    {
        return [
            'configuration' => $configuration,
            'connectionParams' => ['driver' => 'pdo_sqlite', 'memory' => true],
            'typeOverrides' => ['datetime' => 'a type'],
            'mappingOverrides' => ['enum' => 'string'],
        ];
    }
}
