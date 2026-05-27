<?php

declare(strict_types=1);

namespace ampfTest;

use ampf\ApplicationContext;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @phpcs:disable PSR12.Classes.AnonClassDeclaration.SpaceAfterKeyword
 * @phpcs:disable SlevomatCodingStandard.ControlStructures.JumpStatementsSpacing.IncorrectLinesCountAfterControlStructure
 * @phpcs:disable SlevomatCodingStandard.ControlStructures.JumpStatementsSpacing.IncorrectLinesCountAfterLastControlStructure
 *
 * @covers \ampf\ApplicationContext
 */
class ApplicationContextTest extends TestCase
{
    protected ApplicationContext $out;

    public static function provideTestMergeConfig(): Generator
    {
        $i = 1;

        yield $i++ . ' two empty configs result in empty config' => [
            [],
            [],
            [],
        ];

        yield $i++ . ' unknown value from config2 is being taken into config1' => [
            [
                'config1value' => 'foobarbaz1',
            ],
            [
                'config2value' => 'foobarbaz2',
            ],
            [
                'config1value' => 'foobarbaz1',
                'config2value' => 'foobarbaz2',
            ],
        ];

        yield $i++ . ' nullvalues from config2 are being taken' => [
            [
                'config1value' => 1,
            ],
            [
                'config2value' => null,
            ],
            [
                'config1value' => 1,
                'config2value' => null,
            ],
        ];

        yield $i++ . ' value from config2 overrides value from config1' => [
            [
                'configvalue' => 'foobarbaz1',
            ],
            [
                'configvalue' => 'foobarbaz2',
            ],
            [
                'configvalue' => 'foobarbaz2',
            ],
        ];

        yield $i++ . ' nested array from config2 is being merged' => [
            [
                'config1value' => 'foobarbaz1',
                'nestedconfig' => [
                    'nested1value' => 'nested1barbaz',
                    'nested2value' => 'nested1barbaz',
                ],
            ],
            [
                'config2value' => 'foobarbaz2',
                'nestedconfig' => [
                    'nested2value' => 'nested2barbaz',
                    'nested3value' => 123,
                ],
            ],
            [
                'config1value' => 'foobarbaz1',
                'config2value' => 'foobarbaz2',
                'nestedconfig' => [
                    'nested1value' => 'nested1barbaz',
                    'nested2value' => 'nested2barbaz',
                    'nested3value' => 123,
                ],
            ],
        ];

        yield $i++ . ' nested array merging only works on first level' => [
            [
                'nestedconfig' => [
                    'nestedvalue1' => 'nested1barbaz',
                    'nestedvalue2' => [
                        'nestednestedvalue' => 'foobarbaz',
                    ],
                ],
            ],
            [
                'nestedconfig' => [
                    'nestedvalue1' => 'nested2barbaz',
                    'nestedvalue2' => [
                        'anothernesting' => 789,
                    ],
                ],
            ],
            [
                'nestedconfig' => [
                    'nestedvalue1' => 'nested2barbaz',
                    'nestedvalue2' => [
                        'anothernesting' => 789,
                    ],
                ],
            ],
        ];

        yield $i++ . ' null values are being copied correctly through nesting' => [
            [
                'nestedconfig' => [
                    'nested1value' => null,
                ],
            ],
            [
                'nestedconfig' => [
                    'nested2value' => null,
                ],
            ],
            [
                'nestedconfig' => [
                    'nested1value' => null,
                    'nested2value' => null,
                ],
            ],
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->out = new class() extends ApplicationContext {
            /**
             * @param array<string, mixed> $config1
             * @param array<string, mixed> $config2
             *
             * @return array<string, mixed>
             */
            public static function mergeConfig(array $config1, array $config2, int $depth = 0): array
            {
                return parent::mergeConfig($config1, $config2, $depth);
            }
        };
    }

    public function testDoctrineConfigIsMerged(): void
    {
        $this->assertDoctrineConfig(
            [
                'connectionParams' => [
                    'user' => 'user',
                ],
            ],
            [
                'connectionParams' => [
                    'unix_socket' => '/run/123',
                    'user' => 'user2',
                ],
            ],
            [
                'connectionParams' => [
                    'unix_socket' => '/run/123',
                    'user' => 'user2',
                ],
            ],
        );
    }

    /**
     * @param array<string, mixed> $config1
     * @param array<string, mixed> $config2
     * @param array<string, mixed> $expectedResult
     */
    #[DataProvider('provideTestMergeConfig')]
    public function testMergeConfig(array $config1, array $config2, array $expectedResult): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->out->mergeConfig($config1, $config2);

        static::assertArraysAreIdenticalIgnoringOrder($expectedResult, $result);
    }

    /**
     * @param array{connectionParams: array<string, string>} $doctrineConfig1
     * @param array{connectionParams: array<string, string>} $doctrineConfig2
     * @param array{connectionParams: array<string, string>} $expectedConfig
     */
    protected function assertDoctrineConfig(array $doctrineConfig1, array $doctrineConfig2, array $expectedConfig): void
    {
        $tmpfile1 = null;
        $tmpfile2 = null;

        try {
            $tmpfile1 = tempnam(sys_get_temp_dir(), (string)mt_rand());
            $tmpfile2 = tempnam(sys_get_temp_dir(), (string)mt_rand());

            if ($tmpfile1 === false || $tmpfile2 === false) {
                throw new RuntimeException();
            }

            $arr1 = ['doctrine' => $doctrineConfig1];
            $arr2 = ['doctrine' => $doctrineConfig2];

            file_put_contents($tmpfile1, '<?php return ' . var_export($arr1, true) . ';');
            file_put_contents($tmpfile2, '<?php return ' . var_export($arr2, true) . ';');

            $config = ApplicationContext::boot([$tmpfile1, $tmpfile2]);

            static::assertSame(
                ['doctrine' => $expectedConfig],
                $config,
            );
        } finally {
            if ($tmpfile1 !== null && $tmpfile1 !== false) {
                unlink($tmpfile1);
            }

            if ($tmpfile2 !== null && $tmpfile2 !== false) {
                unlink($tmpfile2);
            }
        }
    }
}
