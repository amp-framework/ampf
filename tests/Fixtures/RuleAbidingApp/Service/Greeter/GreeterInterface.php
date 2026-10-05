<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\RuleAbidingApp\Service\Greeter;

/** Greets someone. */
interface GreeterInterface
{
    public function greet(string $name): string;
}
