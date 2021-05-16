<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\services\translator\TranslatorService;

trait TranslatorServiceAccess
{
    protected ?TranslatorService $__translatorService = null;

    public function getTranslatorService(): TranslatorService
    {
        if ($this->__translatorService === null) {
            $this->setTranslatorService($this->getBeanFactory()->get('TranslatorService'));
        }

        return $this->__translatorService;
    }

    public function setTranslatorService(TranslatorService $translatorService): void
    {
        $this->__translatorService = $translatorService;
    }

    abstract public function getBeanFactory(): BeanFactory;
}
