<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\App\Controller\Http;

use ampf\Controller\Http\AbstractController;

/**
 * The files of the form's field `files`, a line each: the name the browser sent, the size, PHP's error, and the content
 * PHP received, moved to a temporary file and read back from there (the file is removed again).
 */
final class UploadController extends AbstractController
{
    public function execute(): void
    {
        $request = $this->getRequest();
        $lines = [];

        foreach ($request->getUploadedFiles('files') as $file) {
            $target = (string)tempnam(sys_get_temp_dir(), 'ampf-upload-');
            $content = $file->isUploadedFile() && $file->moveTo($target)
                ? (string)file_get_contents($target)
                : '-';
            unlink($target);

            $lines[] = $file->clientName . ' ' . $file->size . ' ' . $file->error . ' ' . $content;
        }

        $request->addHeader('Content-Type', 'text/plain; charset=UTF-8')->setResponse(implode("\n", $lines));
    }
}
