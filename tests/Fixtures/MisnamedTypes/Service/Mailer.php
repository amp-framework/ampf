<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\MisnamedTypes\service;

/**
 * A type whose namespace is in another case than its file's path (ClassLoadingGuardTest): it loads on a
 * case-insensitive file system only.
 */
final class Mailer
{
}
