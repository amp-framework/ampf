<?php

declare(strict_types=1);

namespace ampf\Request;

/**
 * A file the client uploaded with a form, as PHP received it (HttpRequest::getUploadedFiles()). The client's name is
 * untrusted text, never a path to use; the type the client claims is left out: an application looks at the content.
 */
class UploadedFile
{
    /**
     * @param string $clientName the name the browser sent: untrusted text, never a path to use
     * @param int $size the size in bytes
     * @param string $temporaryPath where PHP keeps the file until the request ends ("" when it kept none)
     * @param int $error PHP's upload error: UPLOAD_ERR_OK, or another of the UPLOAD_ERR_* constants
     */
    public function __construct(
        public readonly string $clientName,
        public readonly int $size,
        public readonly string $temporaryPath,
        public readonly int $error,
    ) {
    }

    /** Whether PHP received the file whole (UPLOAD_ERR_OK). */
    public function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    /** Whether the temporary path is a file uploaded with this request (PHP's is_uploaded_file()). */
    public function isUploadedFile(): bool
    {
        return $this->isUploaded($this->temporaryPath);
    }

    /**
     * Moves the file to a path the application chose — never one made of the client's name —, replacing a file there.
     * False when PHP did not move it: the path is no file of this request's upload, or the target cannot be written.
     */
    public function moveTo(string $target): bool
    {
        return $this->moveUploaded($this->temporaryPath, $target);
    }

    /** PHP's is_uploaded_file(): the seam a test answers in PHP's place (the command line has no upload). */
    protected function isUploaded(string $path): bool
    {
        return is_uploaded_file($path);
    }

    /** PHP's move_uploaded_file(): the seam a test replaces (the command line has no upload). */
    protected function moveUploaded(string $path, string $target): bool
    {
        return move_uploaded_file($path, $target);
    }
}
