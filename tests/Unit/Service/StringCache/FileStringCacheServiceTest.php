<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\StringCache;

use ampf\Service\StringCache\FileStringCacheService;
use ampf\Tests\Support\AlwaysSweepingFileCache;
use ampf\Tests\Support\NeverSweepingFileCache;
use ampf\Tests\Support\SeamFileStringCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

#[CoversClass(FileStringCacheService::class)]
final class FileStringCacheServiceTest extends TestCase
{
    private string $directory;

    private string $workingDirectory;

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDamagedEntries(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [" \n"];
        yield 'no JSON' => ['<p>a page</p>'];
        yield 'no object' => ['"<p>a page</p>"'];
        yield 'no time' => ['{"string":"<p>a page</p>"}'];
        yield 'no string' => ['{"until":9999999999}'];
        yield 'a time that is no number' => ['{"until":"9999999999","string":"<p>a page</p>"}'];
        yield 'a string that is no string' => ['{"until":9999999999,"string":42}'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideConfigsThatAreRefused(): iterable
    {
        $directory = sys_get_temp_dir();

        yield 'no block' => [[], 'The configuration\'s stringfilecache must be an array, not null.'];
        yield 'a block that is no array' => [
            ['stringfilecache' => 'on'],
            'The configuration\'s stringfilecache must be an array, not string.',
        ];
        yield 'no directory' => [
            ['stringfilecache' => []],
            'The configuration\'s stringfilecache.cachedir must name a directory, not null.',
        ];
        yield 'a directory that is no string' => [
            ['stringfilecache' => ['cachedir' => ['cache']]],
            'The configuration\'s stringfilecache.cachedir must name a directory, not array.',
        ];
        yield 'a directory that does not exist' => [
            ['stringfilecache' => ['cachedir' => $directory . '/ampf-missing-cache']],
            'The cache directory ' . $directory . '/ampf-missing-cache is no directory this process can write to.',
        ];
        yield 'a file' => [
            ['stringfilecache' => ['cachedir' => __FILE__]],
            'The cache directory ' . __FILE__ . ' is no directory this process can write to.',
        ];
        yield 'a time to live that is no number' => [
            ['stringfilecache' => ['cachedir' => $directory, 'defaultttl' => 'an hour']],
            'The configuration\'s stringfilecache.defaultttl must be a number of seconds, not string.',
        ];
    }

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

    public function testAMissRemovesNothing(): void
    {
        $cache = $this->clockedCache(1_700_000_000);

        self::assertFalse($cache->get('page'));
        self::assertNotContains('remove', $cache->getCalledMethods(), 'no entry, no file to remove');
    }

    public function testTheSweepRemovesTheExpiredEntriesAndNothingElse(): void
    {
        $cache = $this->newCache();
        $cache->set('fresh', 'kept');
        $cache->set('stale_one', 'gone', -10);
        $cache->set('stale_two', 'gone', -1);
        file_put_contents($this->directory . '/damaged.asc', 'not an entry');
        file_put_contents($this->directory . '/empty.asc', '');
        file_put_contents($this->directory . '/prefixed.asc', 'garbage{"until":9999999999,"string":"x"}');

        // What else the directory holds, old or not: other tools' caches, a directory, names nearly like an entry's
        mkdir($this->directory . '/doctrine');
        file_put_contents($this->directory . '/doctrine/mapping.php', '<?php return [];');
        file_put_contents($this->directory . '/vendor.tar.gz', 'archive');
        touch($this->directory . '/vendor.tar.gz', time() - 7_200);
        mkdir($this->directory . '/folder.asc');

        foreach (['phpstan.asc.bak', 'not a key.asc', '.asc', 'x .asc', ' x.asc'] as $name) {
            file_put_contents($this->directory . '/' . $name, '{"until":1,"string":"x"}');
        }

        self::assertSame(5, $cache->sweep());

        self::assertSame(
            [' x.asc', '.asc', 'doctrine', 'folder.asc', 'fresh.asc', 'not a key.asc', 'phpstan.asc.bak', 'vendor.tar.gz', 'x .asc'],
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

    public function testATemporaryFileOfTheFirstSecondOf1970IsSwept(): void
    {
        $cache = $this->newCache();
        $temporary = $this->directory . '/page.asc.0123456789abcdef.tmp';
        file_put_contents($temporary, '{"until":');
        touch($temporary, 0);

        self::assertSame(1, $cache->sweep());
    }

    public function testOnlyTheCachesOwnTemporaryFilesAreSwept(): void
    {
        $cache = $this->newCache();
        $names = [
            ' page.asc.0123456789abcdef.tmp',
            'page.asc.0123456789abcdef.tmp.bak',
            "page.asc.0123456789abcdef.tmp\n",
            'page.asc.0123456789abcde.tmp',
            'page.0123456789abcdef.tmp',
        ];

        foreach ($names as $name) {
            file_put_contents($this->directory . '/' . $name, '{"until":');
            touch($this->directory . '/' . $name, time() - 7_200);
        }

        mkdir($this->directory . '/folder.asc.0123456789abcdef.tmp');
        touch($this->directory . '/folder.asc.0123456789abcdef.tmp', time() - 7_200);

        self::assertSame(0, $cache->sweep());
        self::assertCount(6, $this->listDirectory());
    }

    public function testAnEntryIsServedUpToTheSecondItsTimeIsUp(): void
    {
        $cache = $this->clockedCache(1_700_000_000);
        $cache->set('page', 'content', 10);

        $cache->setNow(1_700_000_010);
        self::assertSame('content', $cache->get('page'));

        $cache->setNow(1_700_000_011);
        self::assertFalse($cache->get('page'));
    }

    public function testASweepKeepsAnEntryUpToTheSecondItsTimeIsUp(): void
    {
        $cache = $this->clockedCache(1_700_000_000);
        $cache->set('page', 'content', 10);

        $cache->setNow(1_700_000_010);
        self::assertSame(0, $cache->sweep());

        $cache->setNow(1_700_000_011);
        self::assertSame(1, $cache->sweep());
    }

    public function testATemporaryFileIsSweptOnceItIsOlderThanAnHour(): void
    {
        $cache = $this->clockedCache(1_700_000_000);
        $temporary = $cache->nameTemporaryFile($this->directory . '/page.asc');
        file_put_contents($temporary, '{"until":');
        touch($temporary, 1_700_000_000 - 3_600);

        self::assertSame(0, $cache->sweep(), 'an hour old');

        touch($temporary, 1_700_000_000 - 3_601);
        self::assertSame(1, $cache->sweep(), 'older');
    }

    public function testATemporaryFileIsTheEntrysPathWithSixteenRandomHexCharacters(): void
    {
        $cache = $this->clockedCache(1_700_000_000);

        self::assertMatchesRegularExpression(
            '/^\/cache\/page\.asc\.[0-9a-f]{16}\.tmp$/D',
            $cache->nameTemporaryFile('/cache/page.asc'),
        );
        self::assertNotSame($cache->nameTemporaryFile('/cache/page.asc'), $cache->nameTemporaryFile('/cache/page.asc'));
    }

    public function testAWriteDrawsASweepOnceInAHundredTimes(): void
    {
        $cache = new SeamFileStringCache(1_700_000_000, new Randomizer(new Mt19937(7)));
        $reference = new Randomizer(new Mt19937(7));
        $expected = [];
        $drawn = [];

        for ($write = 0; $write < 500; $write++) {
            $expected[] = $reference->getInt(1, 100) === 1;
            $drawn[] = $cache->drawsASweep();
        }

        self::assertSame($expected, $drawn);
        self::assertContains(true, $drawn);
        self::assertContains(false, $drawn);
    }

    public function testASubclassChangesTheStepsOfTheCache(): void
    {
        $cache = $this->clockedCache(1_700_000_000);
        $cache->set('page', 'content');
        $cache->get('page');
        $cache->setNow(1_800_000_000);
        $cache->sweep();

        self::assertSame(
            ['getCacheDir', 'getPath', 'isCorrectKey', 'now', 'randomizer', 'readUntil', 'remove', 'temporaryPath'],
            $cache->getCalledMethods(),
        );
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

    #[DataProvider('provideDamagedEntries')]
    public function testADamagedEntryIsNoEntryAndGoes(string $content): void
    {
        $cache = $this->newCache();
        file_put_contents($this->directory . '/page.asc', $content);

        self::assertFalse($cache->get('page'));
        self::assertFileDoesNotExist($this->directory . '/page.asc');
    }

    public function testABlankStringIsNoEntry(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A blank string is no cache entry: get() could not tell it from none.');

        $this->newCache()->set('page', " \n");
    }

    public function testAKeyIsLettersDigitsAndUnderscoresDotsAndDashes(): void
    {
        $cache = $this->newCache();

        self::assertTrue($cache->set('page-1_a.b', 'content'));
        self::assertSame('content', $cache->get('page-1_a.b'));

        foreach (['../page', 'a/b', 'a b', '', "page\n"] as $key) {
            try {
                $cache->get($key);
                self::fail('took the key ' . $key);
            } catch (RuntimeException $e) {
                self::assertSame(
                    'The cache key ' . $key . ' has a character other than letters, digits and _.-.',
                    $e->getMessage(),
                );
            }
        }
    }

    public function testAnEntryThatCannotBeWrittenIsReportedAndLeavesNothingBehind(): void
    {
        $cache = $this->newCache();
        mkdir($this->directory . '/page.asc');

        try {
            $cache->set('page', 'content');
            self::fail('wrote over a directory');
        } catch (RuntimeException $e) {
            self::assertSame('Could not write the cache entry page.', $e->getMessage());
        }

        self::assertSame(['page.asc'], $this->listDirectory(), 'the temporary file is gone');
    }

    public function testADirectoryThatCannotBeWrittenIsReported(): void
    {
        $cache = $this->newCache();
        chmod($this->directory, 0o500);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Could not write the cache entry page.');

            $cache->set('page', 'content');
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testTheTimeToLiveIsTheConfigurationsUnlessTheWriteNamesOne(): void
    {
        $cache = $this->newCache();

        $cache->set('page', 'content');
        self::assertStringStartsWith(
            '{"until":' . (time() + 60) . ',',
            (string)file_get_contents($this->directory . '/page.asc'),
        );

        $cache->set('page', 'content', 5);
        self::assertStringStartsWith(
            '{"until":' . (time() + 5) . ',',
            (string)file_get_contents($this->directory . '/page.asc'),
        );

        $cache = new NeverSweepingFileCache();
        $cache->setConfig(['stringfilecache' => ['cachedir' => $this->directory, 'defaultttl' => '120']]);
        $cache->set('page', 'content');
        self::assertStringStartsWith(
            '{"until":' . (time() + 120) . ',',
            (string)file_get_contents($this->directory . '/page.asc'),
        );

        $cache = new NeverSweepingFileCache();
        $cache->setConfig(['stringfilecache' => ['cachedir' => $this->directory, 'defaultttl' => null]]);
        $cache->set('page', 'content');
        self::assertStringStartsWith(
            '{"until":' . (time() + 3_600) . ',',
            (string)file_get_contents($this->directory . '/page.asc'),
            'an hour',
        );
    }

    public function testAWriteMaySweep(): void
    {
        $cache = new FileStringCacheService();
        $cache->setConfig(['stringfilecache' => ['cachedir' => $this->directory]]);

        self::assertTrue($cache->set('page', 'content'), 'one in a hundred sweeps, whichever this is');
        self::assertSame('content', $cache->get('page'));
    }

    public function testWithoutAConfigurationThereIsNoDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The string cache has no directory.');

        new FileStringCacheService()->get('page');
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideConfigsThatAreRefused')]
    public function testAConfigurationWithoutAWritableDirectoryOrWithAWrongTimeToLiveIsRefused(
        array $config,
        string $message,
    ): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        new FileStringCacheService()->setConfig($config);
    }

    public function testADirectoryThisProcessCannotWriteToIsRefused(): void
    {
        chmod($this->directory, 0o500);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                'The cache directory ' . $this->directory . ' is no directory this process can write to.',
            );

            new FileStringCacheService()->setConfig(['stringfilecache' => ['cachedir' => $this->directory]]);
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ampf-file-cache-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o700);

        // Inside the test's directory, a path that lost its directory (a mutant's) lands there too
        $this->workingDirectory = (string)getcwd();
        chdir($this->directory);
    }

    protected function tearDown(): void
    {
        chdir($this->workingDirectory);
        $this->remove($this->directory);
    }

    private function clockedCache(int $now): SeamFileStringCache
    {
        $cache = new SeamFileStringCache($now);
        $cache->setConfig(['stringfilecache' => ['cachedir' => $this->directory, 'defaultttl' => 60]]);

        return $cache;
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
