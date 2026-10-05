<?php

declare(strict_types=1);

namespace ampf\Tests\Support;

/**
 * The recording request that is told what PHP's `post_max_size` is, as an application's request that changes the
 * protected method that reads it would: a test cannot set the limit, which PHP reads when it starts.
 */
final class PostLimitHttpRequest extends RecordingHttpRequest
{
    /**
     * @param int $limit what `postMaxSize()` answers: 0 for no limit
     * @param array<string, string> $server
     */
    public function __construct(private readonly int $limit, array $server = [])
    {
        parent::__construct($server);
    }

    protected function postMaxSize(): int
    {
        return $this->limit;
    }
}
