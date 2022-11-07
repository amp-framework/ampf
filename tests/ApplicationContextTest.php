<?php

declare(strict_types=1);

namespace ampfTest;

use ampf\ApplicationContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @covers \ampf\ApplicationContext
 */
class ApplicationContextTest extends TestCase
{
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
