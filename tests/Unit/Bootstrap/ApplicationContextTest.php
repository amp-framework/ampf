<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Bootstrap;

use ampf\Bootstrap\ApplicationContext;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ApplicationContext::class)]
final class ApplicationContextTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $files = [];

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
                'nestedconfig' => [
                    'nested1value' => 'nested1barbaz',
                    'nested2value' => 'nested2barbaz',
                    'nested3value' => 123,
                ],
                'config2value' => 'foobarbaz2',
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

        yield $i++ . ' inside a block, a scalar replaces an array' => [
            [
                'doctrine' => ['mappingOverrides' => ['enum' => 'string']],
            ],
            [
                'doctrine' => ['mappingOverrides' => null],
            ],
            [
                'doctrine' => ['mappingOverrides' => null],
            ],
        ];

        yield $i++ . ' an array replaces a scalar' => [
            [
                'translation.dir' => null,
            ],
            [
                'translation.dir' => ['de' => '/translations'],
            ],
            [
                'translation.dir' => ['de' => '/translations'],
            ],
        ];

        yield $i++ . ' existing keys keep their place, new ones follow' => [
            [
                'routes' => ['catch-all' => 'first', 'home' => 'second'],
            ],
            [
                'routes' => ['added' => 'third', 'home' => 'replaced'],
            ],
            [
                'routes' => ['catch-all' => 'first', 'home' => 'replaced', 'added' => 'third'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config1
     * @param array<string, mixed> $config2
     * @param array<string, mixed> $expectedResult
     */
    #[DataProvider('provideTestMergeConfig')]
    public function testMergeConfig(array $config1, array $config2, array $expectedResult): void
    {
        self::assertSame($expectedResult, ApplicationContext::boot([$this->file($config1), $this->file($config2)]));
    }

    public function testNoFilesAreNoConfiguration(): void
    {
        self::assertSame([], ApplicationContext::boot());
        self::assertSame([], ApplicationContext::boot([]));
    }

    public function testTheFilesAreMergedInTheirOrder(): void
    {
        $config = ApplicationContext::boot([
            $this->file(['doctrine' => ['connectionParams' => ['user' => 'user']], 'viewDirectory' => '/first']),
            $this->file(['doctrine' => ['connectionParams' => ['unix_socket' => '/run/123', 'user' => 'user2']]]),
            $this->file(['viewDirectory' => '/third']),
        ]);

        self::assertSame(
            [
                'doctrine' => ['connectionParams' => ['unix_socket' => '/run/123', 'user' => 'user2']],
                'viewDirectory' => '/third',
            ],
            $config,
        );
    }

    public function testAFileRunsInAScopeOfItsOwn(): void
    {
        $config = ApplicationContext::boot([
            $this->file(['first' => 1]),
            $this->source(
                "\$config = ['clobbered' => true];\n\$configFile = 'x';\n\nreturn ['second' => 2, 'saw' => isset(\$fileConfig) || isset(\$configFiles)];",
            ),
        ]);

        self::assertSame(['first' => 1, 'second' => 2, 'saw' => false], $config);
    }

    public function testAFileThatReturnsNoArrayIsRefused(): void
    {
        $file = $this->source('return 42;');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configuration file ' . $file . ': Expected an array keyed by strings, got int.',
        );

        ApplicationContext::boot([$file]);
    }

    public function testAFileThatReturnsAListIsRefused(): void
    {
        $file = $this->file(['beans', 'routes']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configuration file ' . $file . ': Expected an array keyed by strings, found the key 0.',
        );

        ApplicationContext::boot([$file]);
    }

    public function testABlockCannotBeReplacedByAScalar(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configuration\'s doctrine cannot be merged: Expected an array keyed by strings, got string.',
        );

        ApplicationContext::boot(
            [$this->file(['doctrine' => ['configuration' => null]]), $this->file(['doctrine' => 'none'])],
        );
    }

    public function testAListCannotBeMerged(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The configuration\'s paths cannot be merged: Expected an array keyed by strings, found the key 0.',
        );

        ApplicationContext::boot([$this->file(['paths' => ['/a']]), $this->file(['paths' => ['/b']])]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    /**
     * A configuration file returning the array.
     *
     * @param array<mixed> $config
     */
    private function file(array $config): string
    {
        return $this->source('return ' . var_export($config, true) . ';');
    }

    /**
     * A configuration file of this code.
     */
    private function source(string $code): string
    {
        $file = tempnam(sys_get_temp_dir(), 'ampf-config-');
        self::assertIsString($file);
        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n" . $code . "\n");
        $this->files[] = $file;

        return $file;
    }
}
