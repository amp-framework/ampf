<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\services\cache\string\StringCacheService;

trait StringCacheServiceAccess
{
    protected ?StringCacheService $__stringCacheService = null;

    abstract public function getBeanFactory(): BeanFactory;

    public function getStringCacheService(): StringCacheService
    {
        if ($this->__stringCacheService === null) {
            $this->setStringCacheService($this->getBeanFactory()->get('StringCacheService'));
        }

        return $this->__stringCacheService;
    }

    public function setStringCacheService(StringCacheService $stringCacheService): void
    {
        $this->__stringCacheService = $stringCacheService;
    }
}
