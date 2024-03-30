<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\services\xsrfToken\XsrfTokenService;

trait XsrfTokenServiceAccess
{
    protected ?XsrfTokenService $__xsrfTokenService = null;

    abstract public function getBeanFactory(): BeanFactory;

    public function getXsrfTokenService(): XsrfTokenService
    {
        if ($this->__xsrfTokenService === null) {
            $xsrfTokenService = $this->getBeanFactory()->get('XsrfTokenService');
            assert($xsrfTokenService instanceof XsrfTokenService);
            $this->setXsrfTokenService($xsrfTokenService);
        }
        assert($this->__xsrfTokenService !== null);

        return $this->__xsrfTokenService;
    }

    public function setXsrfTokenService(XsrfTokenService $xsrfTokenService): void
    {
        $this->__xsrfTokenService = $xsrfTokenService;
    }
}
