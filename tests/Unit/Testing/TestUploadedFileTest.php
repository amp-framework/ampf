<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing;

use ampf\Testing\TestUploadedFile;
use ampf\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestUploadedFile::class)]
final class TestUploadedFileTest extends TestCase
{
    private TemporaryDirectory $directory;

    public function testAFileTheTestWroteCountsAsUploaded(): void
    {
        $path = $this->file('upload', 'Hello');
        $file = new TestUploadedFile('a.txt', 5, $path, UPLOAD_ERR_OK);

        self::assertTrue($file->isUploadedFile());
        self::assertFalse(
            new TestUploadedFile('a.txt', 5, $path . '.gone', UPLOAD_ERR_OK)->isUploadedFile(),
            'a path without a file',
        );
        self::assertSame('a.txt', $file->clientName);
    }

    public function testItIsMovedAsPhpMovesAnUploadedFile(): void
    {
        $path = $this->file('upload', 'Hello');
        $target = $this->file('target', 'replaced');

        self::assertTrue(new TestUploadedFile('a.txt', 5, $path, UPLOAD_ERR_OK)->moveTo($target));
        self::assertStringEqualsFile($target, 'Hello');
        self::assertFileDoesNotExist($path);
    }

    public function testWhatCannotBeMovedIsNot(): void
    {
        $path = $this->file('upload', 'Hello');
        $file = new TestUploadedFile('a.txt', 5, $path, UPLOAD_ERR_OK);

        self::assertFalse($file->moveTo($this->directory->getPath() . '/missing/target'), 'no such directory');
        self::assertStringEqualsFile($path, 'Hello');
        $gone = new TestUploadedFile('b.txt', 0, $path . '.gone', UPLOAD_ERR_OK);

        self::assertFalse($gone->moveTo($this->directory->getPath() . '/b'), 'no such file');
        self::assertFileDoesNotExist($this->directory->getPath() . '/b');
    }

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory('ampf-uploaded-file');
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    /** A file of the test's directory with the content. */
    private function file(string $name, string $content): string
    {
        $path = $this->directory->getPath() . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
