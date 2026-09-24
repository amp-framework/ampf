<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Service\Session\SessionServiceInterface;

trait SessionServiceAccess
{
    use AbstractAccess;

    protected ?SessionServiceInterface $__sessionService = null;

    public function getSessionService(): SessionServiceInterface
    {
        if ($this->__sessionService === null) {
            $object = $this->getBeanFactory()->get(SessionServiceInterface::class);
            assert($object instanceof SessionServiceInterface);
            $this->setSessionService($object);
        }

        assert($this->__sessionService instanceof SessionServiceInterface);

        return $this->__sessionService;
    }

    public function setSessionService(SessionServiceInterface $object): void
    {
        $this->__sessionService = $object;
    }
}
