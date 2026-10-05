<?php

declare(strict_types=1);

namespace ampf\Testing;

use ampf\Request\UploadedFile;

/**
 * An uploaded file in a test (TestHttpRequest's): the command line has no upload, so the temporary path is a file the
 * test wrote, which counts as uploaded with the request, and moveTo() moves it as PHP moves an uploaded file.
 */
class TestUploadedFile extends UploadedFile
{
    /** A file at the path counts as uploaded with the request. */
    protected function isUploaded(string $path): bool
    {
        return is_file($path);
    }

    /** Moves the file to the target, replacing a file there; false when it cannot be moved. */
    protected function moveUploaded(string $path, string $target): bool
    {
        // @: a failure is this method's false, as it is move_uploaded_file()'s, not a warning besides it
        return @rename($path, $target);
    }
}
