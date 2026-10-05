<?php

declare(strict_types=1);

namespace ampf\Bootstrap;

/**
 * What a stack trace in a log says: the calls, where they are and their arguments, so that a failure that comes from
 * production can be debugged from its log. PHP lists the arguments unless `zend.exception_ignore_args` is on (php.ini-production
 * has it on; php.ini-development and no php.ini at all have it off). What must not be in a log is a parameter that is marked
 * `#[SensitiveParameter]` — a password, a key —, which PHP lists as a `SensitiveParameterValue` whatever the setting says:
 * this setting hides nothing else, and nothing else is hidden by it.
 */
class TraceSettings
{
    /** The first thing an entry point does: every exception made after it lists the arguments of the calls in its trace. */
    public static function apply(): void
    {
        ini_set('zend.exception_ignore_args', '0');
    }
}
