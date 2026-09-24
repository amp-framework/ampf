<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Service\Hasher\HasherServiceInterface;

trait HasherServiceAccess
{
    use AbstractAccess;

    protected ?HasherServiceInterface $__hasherService = null;

    public function getHasherService(): HasherServiceInterface
    {
        if ($this->__hasherService === null) {
            $object = $this->getBeanFactory()->get(HasherServiceInterface::class);
            assert($object instanceof HasherServiceInterface);
            $this->setHasherService($object);
        }

        assert($this->__hasherService instanceof HasherServiceInterface);

        return $this->__hasherService;
    }

    public function setHasherService(HasherServiceInterface $object): void
    {
        $this->__hasherService = $object;
    }
}
