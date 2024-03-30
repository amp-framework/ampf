<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\services\timel10n\TimeL10nService;

trait TimeL10nServiceAccess
{
    protected ?TimeL10nService $__timeL10nService = null;

    abstract public function getBeanFactory(): BeanFactory;

    public function getTimeL10nService(): TimeL10nService
    {
        if ($this->__timeL10nService === null) {
            $this->setTimeL10nService($this->getBeanFactory()->get('TimeL10nService'));
        }

        return $this->__timeL10nService;
    }

    public function setTimeL10nService(TimeL10nService $timeL10nService): void
    {
        $this->__timeL10nService = $timeL10nService;
    }
}
