<?php

declare(strict_types=1);

use ampf\Tests\Fixtures\RuleAbidingApp\Service\Greeter\Greeter;
use ampf\Tests\Fixtures\RuleAbidingApp\Service\Greeter\GreeterInterface;

/*
 * An application that keeps every rule the guards of ampf\Testing\Guard check (their tests): its service keyed by the
 * interface its class implements, a prototype keyed by its class; routes that name controller beans, their catch-alls
 * last; route constants for every route; the access trait of its service generated.
 */
return [
    'beans' => [
        GreeterInterface::class => ['class' => Greeter::class],
        Greeter::class => ['class' => Greeter::class, 'scope' => 'prototype'],
    ],
];
