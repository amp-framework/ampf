<?php

declare(strict_types=1);

namespace ampf\Testing;

use ampf\Request\CliRequest;

/**
 * The real command line request with its arguments given by the test instead of PHP's `$argv`; flush() prints nothing,
 * and getResponseBody() is what it would have printed.
 */
class TestCliRequest extends CliRequest
{
    /**
     * @param list<string> $arguments the route followed by its arguments, as they follow `php bin/index.php`
     */
    public function __construct(array $arguments = [])
    {
        parent::__construct(['bin/index.php', ...$arguments]);
    }

    /**
     * Prints nothing: the response stays for getResponseBody().
     *
     * @phpstan-pure
     */
    public function flush(): self
    {
        return $this;
    }

    /** What the command set as its response: what flush() would print. */
    public function getResponseBody(): string
    {
        return $this->responseBody ?? '';
    }
}
