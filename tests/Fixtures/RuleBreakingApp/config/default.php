<?php

declare(strict_types=1);

use ampf\Service\Hasher\HasherService;
use ampf\Service\TimeL10n\TimeL10nService;
use ampf\Tests\Fixtures\RuleBreakingApp\Service\Mailer\MailerInterface;

/*
 * An application that breaks each rule the guards of ampf\Testing\Guard check (their tests). Its beans: one keyed by no
 * type, one by a type its class is not, a singleton keyed by a class, one without a class, one whose definition is
 * no array — and a prototype keyed by its class, which is no problem.
 */
return [
    'beans' => [
        'Mailer' => ['class' => TimeL10nService::class],
        MailerInterface::class => ['class' => TimeL10nService::class],
        TimeL10nService::class => ['class' => TimeL10nService::class],
        'Broken' => ['scope' => 'prototype'],
        'NotADefinition' => 'a text',
        HasherService::class => ['class' => HasherService::class, 'scope' => 'prototype'],
    ],
];
