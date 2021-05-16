<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\services\hasher\HasherService;

trait HasherServiceAccess
{
    protected ?HasherService $__hasherService = null;

    public function getHasherService(): HasherService
    {
        if ($this->__hasherService === null) {
            $this->setHasherService($this->getBeanFactory()->get('HasherService'));
        }

        return $this->__hasherService;
    }

    public function setHasherService(HasherService $hasherService): void
    {
        $this->__hasherService = $hasherService;
    }

    abstract public function getBeanFactory(): BeanFactory;
}
