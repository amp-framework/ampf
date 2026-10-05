<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Request;

use ampf\Request\HttpRequest;
use ampf\Request\UploadedFile;
use ampf\Tests\Support\RecordingHttpRequest;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The reader for uploaded files: `$_FILES` as PHP shapes it — one field's names, sizes, temporary paths and errors in
 * trees of one shape — and as nobody should trust it to be.
 */
#[CoversClass(HttpRequest::class)]
final class HttpRequestUploadedFilesTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, string|array<mixed>>, string, list<array{string, int, string, int}>}>
     */
    public static function provideFields(): iterable
    {
        yield 'a single file' => [
            ['photo' => self::field('a.jpg', 10, '/tmp/php1', UPLOAD_ERR_OK)],
            'photo',
            [['a.jpg', 10, '/tmp/php1', UPLOAD_ERR_OK]],
        ];
        yield 'the files of a multiple input, in their order' => [
            ['photos' => self::field(
                ['c.jpg', 'a.jpg', 'b.pdf'],
                [3, 1, 2],
                ['/tmp/php3', '/tmp/php1', '/tmp/php2'],
                [0, 0, 0],
            )],
            'photos',
            [['c.jpg', 3, '/tmp/php3', 0], ['a.jpg', 1, '/tmp/php1', 0], ['b.pdf', 2, '/tmp/php2', 0]],
        ];
        yield 'an input left empty' => [
            ['photo' => self::field('', 0, '', UPLOAD_ERR_NO_FILE)],
            'photo',
            [],
        ];
        yield 'an input left empty among chosen files' => [
            ['photos' => self::field(['a.jpg', '', 'b.jpg'], [1, 0, 2], ['/tmp/php1', '', '/tmp/php2'], [0, 4, 0])],
            'photos',
            [['a.jpg', 1, '/tmp/php1', 0], ['b.jpg', 2, '/tmp/php2', 0]],
        ];
        yield 'files PHP refused, with their errors' => [
            [
                'photos' => self::field(
                    ['big.jpg', 'cut.jpg', 'lost.jpg'],
                    [0, 512, 0],
                    ['', '/tmp/php1', ''],
                    [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_TMP_DIR],
                ),
            ],
            'photos',
            [['big.jpg', 0, '', 1], ['cut.jpg', 512, '/tmp/php1', 3], ['lost.jpg', 0, '', 6]],
        ];
        yield 'nested names, every file below the field' => [
            [
                'receipt' => self::field(
                    ['scan' => 'x.pdf', 'pages' => ['y.jpg', 'z.jpg'], 'note' => ['empty' => '']],
                    ['scan' => 5, 'pages' => [6, 7], 'note' => ['empty' => 0]],
                    ['scan' => '/tmp/php5', 'pages' => ['/tmp/php6', '/tmp/php7'], 'note' => ['empty' => '']],
                    ['scan' => 0, 'pages' => [0, 0], 'note' => ['empty' => 4]],
                ),
            ],
            'receipt',
            [['x.pdf', 5, '/tmp/php5', 0], ['y.jpg', 6, '/tmp/php6', 0], ['z.jpg', 7, '/tmp/php7', 0]],
        ];
        yield 'the field asked for, not another' => [
            [
                'avatar' => self::field('me.png', 4, '/tmp/php4', 0),
                'photo' => self::field('a.jpg', 1, '/tmp/php1', 0),
            ],
            'photo',
            [['a.jpg', 1, '/tmp/php1', 0]],
        ];
        yield 'an absent field' => [['photo' => self::field('a.jpg', 1, '/tmp/php1', 0)], 'avatar', []];
        yield 'a field that is no array' => [['photo' => 'a.jpg'], 'photo', []];
        yield 'a field without its errors' => [
            ['photo' => ['name' => 'a.jpg', 'size' => 1, 'tmp_name' => '/tmp/php1']],
            'photo',
            [],
        ];
        yield 'a field without its names' => [
            ['photo' => ['size' => 1, 'tmp_name' => '/tmp/php1', 'error' => 0]],
            'photo',
            [],
        ];
        yield 'a field without its sizes' => [
            ['photo' => ['name' => 'a.jpg', 'tmp_name' => '/tmp/php1', 'error' => 0]],
            'photo',
            [],
        ];
        yield 'a field without its temporary paths' => [
            ['photo' => ['name' => 'a.jpg', 'size' => 1, 'error' => 0]],
            'photo',
            [],
        ];
        yield 'an error that is no number' => [['photo' => self::field('a.jpg', 1, '/tmp/php1', '0')], 'photo', []];
        yield 'a size that is no number' => [['photo' => self::field('a.jpg', '1', '/tmp/php1', 0)], 'photo', []];
        yield 'a name that is no text' => [['photo' => self::field(7, 1, '/tmp/php1', 0)], 'photo', []];
        yield 'a temporary path that is no text' => [['photo' => self::field('a.jpg', 1, 7, 0)], 'photo', []];
        yield 'single values where the errors are a list' => [
            ['photos' => self::field('a.jpg', 1, '/tmp/php1', [0])],
            'photos',
            [],
        ];
        yield 'lists where the error is a single value' => [
            ['photos' => self::field(['a.jpg'], [1], ['/tmp/php1'], 0)],
            'photos',
            [],
        ];
        yield 'lists shorter than the errors' => [
            ['photos' => self::field(['a.jpg'], [1, 2], ['/tmp/php1', '/tmp/php2'], [0, 0])],
            'photos',
            [['a.jpg', 1, '/tmp/php1', 0]],
        ];
        yield 'a list of the sizes shorter than the errors' => [
            ['photos' => self::field(['a.jpg', 'b.jpg'], [1], ['/tmp/php1', '/tmp/php2'], [0, 0])],
            'photos',
            [['a.jpg', 1, '/tmp/php1', 0]],
        ];
        yield 'a list of the paths shorter than the errors' => [
            ['photos' => self::field(['a.jpg', 'b.jpg'], [1, 2], ['/tmp/php1'], [0, 0])],
            'photos',
            [['a.jpg', 1, '/tmp/php1', 0]],
        ];
    }

    /**
     * A field of `$_FILES` as PHP builds it: the name, the type the client claims, the temporary path, the error and
     * the size, each a value for one file or a tree for several.
     *
     * @return array<string, mixed>
     */
    private static function field(mixed $name, mixed $size, mixed $temporaryPath, mixed $error): array
    {
        return [
            'name' => $name,
            'full_path' => $name,
            'type' => 'application/octet-stream',
            'tmp_name' => $temporaryPath,
            'error' => $error,
            'size' => $size,
        ];
    }

    /**
     * @return array{string, int, string, int}
     */
    private static function described(UploadedFile $file): array
    {
        return [$file->clientName, $file->size, $file->temporaryPath, $file->error];
    }

    /**
     * @param array<string, string|array<mixed>> $files
     * @param list<array{string, int, string, int}> $expected
     */
    #[DataProvider('provideFields')]
    public function testAFieldGivesItsFilesInTheirOrder(array $files, string $key, array $expected): void
    {
        $request = new RecordingHttpRequest(files: $files);

        self::assertSame($expected, array_map(self::described(...), $request->getUploadedFiles($key)));
    }

    #[BackupGlobals(true)]
    public function testTheFilesAreReadFromPhpsArray(): void
    {
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable -- PHP's array is the input here
        $_FILES = [
            'photos' => [
                'name' => ['a.jpg', 'b.jpg'],
                'full_path' => ['a.jpg', 'b.jpg'],
                'type' => ['image/jpeg', 'image/jpeg'],
                'tmp_name' => ['/tmp/php1', '/tmp/php2'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_FORM_SIZE],
                'size' => [1, 0],
            ],
        ];
        // phpcs:enable

        self::assertSame(
            [['a.jpg', 1, '/tmp/php1', 0], ['b.jpg', 0, '/tmp/php2', 2]],
            array_map(self::described(...), new HttpRequest()->getUploadedFiles('photos')),
        );
    }

    public function testASubclassMakesTheFilesItsOwnWay(): void
    {
        $request = new class extends RecordingHttpRequest {
            public function __construct()
            {
                $photo = ['name' => 'a.jpg', 'size' => 1, 'tmp_name' => '/tmp/php1', 'error' => 0];

                parent::__construct(files: ['photo' => $photo]);
            }

            protected function createUploadedFile(
                string $clientName,
                int $size,
                string $temporaryPath,
                int $error,
            ): UploadedFile {
                return new UploadedFile(strtoupper($clientName), $size * 2, $temporaryPath . '.copy', $error + 1);
            }
        };

        self::assertSame(
            [['A.JPG', 2, '/tmp/php1.copy', 1]],
            array_map(self::described(...), $request->getUploadedFiles('photo')),
        );
    }
}
