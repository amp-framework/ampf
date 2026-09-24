<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Service\StringCache\StringCacheServiceInterface;

trait StringCacheServiceAccess
{
    use AbstractAccess;

    protected ?StringCacheServiceInterface $__stringCacheService = null;

    public function getStringCacheService(): StringCacheServiceInterface
    {
        if ($this->__stringCacheService === null) {
            $object = $this->getBeanFactory()->get(StringCacheServiceInterface::class);
            assert($object instanceof StringCacheServiceInterface);
            $this->setStringCacheService($object);
        }

        assert($this->__stringCacheService instanceof StringCacheServiceInterface);

        return $this->__stringCacheService;
    }

    public function setStringCacheService(StringCacheServiceInterface $object): void
    {
        $this->__stringCacheService = $object;
    }
}
