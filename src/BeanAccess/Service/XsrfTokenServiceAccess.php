<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Service\XsrfToken\XsrfTokenServiceInterface;

trait XsrfTokenServiceAccess
{
    use AbstractAccess;

    protected ?XsrfTokenServiceInterface $__xsrfTokenService = null;

    public function getXsrfTokenService(): XsrfTokenServiceInterface
    {
        if ($this->__xsrfTokenService === null) {
            $object = $this->getBeanFactory()->get(XsrfTokenServiceInterface::class);
            assert($object instanceof XsrfTokenServiceInterface);
            $this->setXsrfTokenService($object);
        }

        assert($this->__xsrfTokenService instanceof XsrfTokenServiceInterface);

        return $this->__xsrfTokenService;
    }

    public function setXsrfTokenService(XsrfTokenServiceInterface $object): void
    {
        $this->__xsrfTokenService = $object;
    }
}
