<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\services\session\SessionService;

trait SessionServiceAccess
{
    protected ?SessionService $__sessionService = null;

    abstract public function getBeanFactory(): BeanFactory;

    public function getSessionService(): SessionService
    {
        if ($this->__sessionService === null) {
            $sessionService = $this->getBeanFactory()->get('SessionService');
            assert($sessionService instanceof SessionService);
            $this->setSessionService($sessionService);
        }
        assert($this->__sessionService !== null);

        return $this->__sessionService;
    }

    public function setSessionService(SessionService $sessionService): void
    {
        $this->__sessionService = $sessionService;
    }
}
