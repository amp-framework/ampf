<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\MisnamedTypes\Helper;

/**
 * A type under another name than its file's (ClassLoadingGuardTest). Loading the file twice declares it twice, a fatal
 * error: one test looks its path's type up, once.
 */
final class StringHelper
{
}
