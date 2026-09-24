<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Service\TimeL10n\TimeL10nServiceInterface;

trait TimeL10nServiceAccess
{
    use AbstractAccess;

    protected ?TimeL10nServiceInterface $__timeL10nService = null;

    public function getTimeL10nService(): TimeL10nServiceInterface
    {
        if ($this->__timeL10nService === null) {
            $object = $this->getBeanFactory()->get(TimeL10nServiceInterface::class);
            assert($object instanceof TimeL10nServiceInterface);
            $this->setTimeL10nService($object);
        }

        assert($this->__timeL10nService instanceof TimeL10nServiceInterface);

        return $this->__timeL10nService;
    }

    public function setTimeL10nService(TimeL10nServiceInterface $object): void
    {
        $this->__timeL10nService = $object;
    }
}
