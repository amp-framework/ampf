<?php

declare(strict_types=1);

namespace ampf\BeanAccess;

use ampf\Bean\BeanFactoryInterface;

/** Shared by every bean access trait: the host provides the bean factory (usually through BeanFactoryAccess). */
trait AbstractAccess
{
    abstract public function getBeanFactory(): BeanFactoryInterface;
}
