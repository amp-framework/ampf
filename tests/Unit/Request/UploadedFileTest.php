<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Request\UploadedFile;
use ampf\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadedFile::class)]
final class UploadedFileTest extends TestCase
{
    public function testAFileCarriesWhatPhpReceivedAndTheNameAsTheBrowserSentIt(): void
    {
        $file = new UploadedFile('../../etc/passwd', 1_234, '/tmp/phpA1b2C3', UPLOAD_ERR_OK);

        self::assertSame('../../etc/passwd', $file->clientName, 'text, never a path to use');
        self::assertSame(1_234, $file->size);
        self::assertSame('/tmp/phpA1b2C3', $file->temporaryPath);
        self::assertSame(UPLOAD_ERR_OK, $file->error);
        self::assertTrue($file->isOk());
    }

    public function testAFileWithAnUploadErrorIsNotOk(): void
    {
        foreach (
            [
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE,
                UPLOAD_ERR_PARTIAL,
                UPLOAD_ERR_NO_FILE,
                UPLOAD_ERR_NO_TMP_DIR,
                UPLOAD_ERR_CANT_WRITE,
                UPLOAD_ERR_EXTENSION,
            ] as $error
        ) {
            self::assertFalse(new UploadedFile('photo.jpg', 0, '', $error)->isOk(), 'error ' . $error);
        }
    }

    public function testAFileTheTestWroteIsNoUploadAndIsNotMoved(): void
    {
        $directory = new TemporaryDirectory('ampf-uploaded-file');

        try {
            $written = $directory->getPath() . '/written';
            $target = $directory->getPath() . '/moved';
            file_put_contents($written, 'content');
            $file = new UploadedFile('a.txt', 7, $written, UPLOAD_ERR_OK);

            self::assertFalse($file->isUploadedFile(), 'PHP knows the files of its own upload only');
            self::assertFalse($file->moveTo($target));
            self::assertFileExists($written);
            self::assertFileDoesNotExist($target);
        } finally {
            $directory->remove();
        }
    }

    public function testASubclassAnswersForPhpsUploadFunctions(): void
    {
        $file = new class extends UploadedFile {
            /**
             * @var list<string>
             */
            private array $calls = [];

            public function __construct()
            {
                parent::__construct('a.txt', 7, '/tmp/phpX', UPLOAD_ERR_OK);
            }

            /**
             * @return list<string>
             */
            public function getCalls(): array
            {
                return $this->calls;
            }

            protected function isUploaded(string $path): bool
            {
                $this->calls[] = 'isUploaded ' . $path;

                return true;
            }

            protected function moveUploaded(string $path, string $target): bool
            {
                $this->calls[] = 'moveUploaded ' . $path . ' ' . $target;

                return true;
            }
        };

        self::assertTrue($file->isUploadedFile());
        self::assertTrue($file->moveTo('/srv/store/ab/cd/abcdef'));
        self::assertSame(['isUploaded /tmp/phpX', 'moveUploaded /tmp/phpX /srv/store/ab/cd/abcdef'], $file->getCalls());
    }
}
