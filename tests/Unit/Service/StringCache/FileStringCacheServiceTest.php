<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\StringCache;

use ampf\Service\StringCache\FileStringCacheService;
use ampf\Tests\Support\AlwaysSweepingFileCache;
use ampf\Tests\Support\NeverSweepingFileCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileStringCacheService::class)]
final class FileStringCacheServiceTest extends TestCase
{
    private string $directory;

    public function testAnEntryIsReadUntilItsTimeIsUp(): void
    {
        $cache = $this->newCache();
        $cache->set('page_one', '<p>one</p>');
        $cache->set('page_two', '<p>two</p>', -1);

        self::assertSame('<p>one</p>', $cache->get('page_one'));
        self::assertFalse($cache->get('page_two'), 'expired');
        self::assertFileDoesNotExist($this->directory . '/page_two.asc', 'an expired entry goes when it is read');
        self::assertFalse($cache->get('page_three'));
    }

    public function testTheSweepRemovesTheExpiredEntriesAndNothingElse(): void
    {
        $cache = $this->newCache();
        $cache->set('fresh', 'kept');
        $cache->set('stale_one', 'gone', -10);
        $cache->set('stale_two', 'gone', -1);
        file_put_contents($this->directory . '/damaged.asc', 'not an entry');
        file_put_contents($this->directory . '/empty.asc', '');

        // What else the directory holds: other tools' caches, a directory, a file named nearly like an entry
        mkdir($this->directory . '/doctrine');
        file_put_contents($this->directory . '/doctrine/mapping.php', '<?php return [];');
        file_put_contents($this->directory . '/vendor.tar.gz', 'archive');
        file_put_contents($this->directory . '/phpstan.asc.bak', '{"until":1,"string":"x"}');
        file_put_contents($this->directory . '/not a key.asc', '{"until":1,"string":"x"}');
        mkdir($this->directory . '/folder.asc');

        self::assertSame(4, $cache->sweep());

        self::assertSame(
            ['doctrine', 'folder.asc', 'fresh.asc', 'not a key.asc', 'phpstan.asc.bak', 'vendor.tar.gz'],
            $this->listDirectory(),
        );
        self::assertSame('kept', $cache->get('fresh'));
        self::assertFileExists($this->directory . '/doctrine/mapping.php');
        self::assertSame(0, $cache->sweep(), 'nothing left to sweep');
    }

    public function testTheSweepRemovesTemporaryFilesAWriterLeftBehind(): void
    {
        $cache = $this->newCache();
        $abandoned = $this->directory . '/page.asc.0123456789abcdef.tmp';
        $writing = $this->directory . '/page.asc.fedcba9876543210.tmp';
        file_put_contents($abandoned, '{"until":');
        touch($abandoned, time() - 7_200);
        file_put_contents($writing, '{"until":');

        self::assertSame(1, $cache->sweep());
        self::assertSame(['page.asc.fedcba9876543210.tmp'], $this->listDirectory(), 'a write in progress stays');
    }

    public function testAWriteSweepsNowAndThen(): void
    {
        $cache = new AlwaysSweepingFileCache();
        $cache->setConfig(['stringfilecache' => ['cachedir' => $this->directory]]);
        $cache->set('stale', 'gone', -1);
        $cache->set('fresh', 'kept');

        self::assertSame(['fresh.asc'], $this->listDirectory());
    }

    /** A write replaces the file in one step: a reader sees the old entry or the new one, never a part of one. */
    public function testAWriteReplacesTheEntryAtOnce(): void
    {
        $cache = $this->newCache();
        $cache->set('page', str_repeat('old ', 10_000));
        $before = fileinode($this->directory . '/page.asc');

        $cache->set('page', str_repeat('new ', 10_000));

        self::assertNotSame($before, fileinode($this->directory . '/page.asc'), 'a new file renamed over the old one');
        self::assertSame(str_repeat('new ', 10_000), $cache->get('page'));
        self::assertSame(['page.asc'], $this->listDirectory(), 'no temporary file left');
    }

    public function testAStringJsonCannotCarryIsNotCached(): void
    {
        $cache = $this->newCache();

        self::assertFalse($cache->set('page', "not utf-8: \xC3\x28"));
        self::assertSame([], $this->listDirectory());
    }

    public function testWithoutTheSettingTheCacheIsOn(): void
    {
        $cache = new NeverSweepingFileCache();
        $cache->setConfig(['stringfilecache' => ['cachedir' => $this->directory]]);

        self::assertTrue($cache->set('page', 'content'));
        self::assertSame('content', $cache->get('page'));
    }

    public function testSwitchedOffNothingIsWrittenOrServed(): void
    {
        $this->newCache()->set('page', 'an earlier page');
        $cache = new NeverSweepingFileCache();
        $cache->setConfig(['stringfilecache' => ['cachedir' => $this->directory, 'enabled' => false]]);

        self::assertFalse($cache->set('other', 'content'));
        self::assertFalse($cache->set('blank', ' '), 'nothing is checked either');
        self::assertFalse($cache->get('page'), 'not even the page that is there');
        self::assertSame(['page.asc'], $this->listDirectory());
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ampf-file-cache-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o700);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    private function newCache(): FileStringCacheService
    {
        $cache = new NeverSweepingFileCache();
        $cache->setConfig(['stringfilecache' => ['cachedir' => $this->directory, 'defaultttl' => 60]]);

        return $cache;
    }

    /**
     * @return list<string>
     */
    private function listDirectory(): array
    {
        $names = array_values(array_diff((array)scandir($this->directory), ['.', '..']));
        sort($names);

        return array_map(strval(...), $names);
    }

    private function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (array_diff((array)scandir($path), ['.', '..']) as $name) {
                $this->remove($path . '/' . strval($name));
            }

            rmdir($path);

            return;
        }

        unlink($path);
    }
}
