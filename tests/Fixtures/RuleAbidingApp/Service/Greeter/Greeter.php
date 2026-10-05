<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\RuleAbidingApp\Service\Greeter;

/** Greets someone by name. */
class Greeter implements GreeterInterface
{
    public function greet(string $name): string
    {
        return 'Hello ' . $name . '!';
    }
}
