<?php

declare(strict_types=1);

namespace ampf\beans\access;

use ampf\beans\BeanFactory;
use ampf\services\translator\TranslatorService;

trait TranslatorServiceAccess
{
    protected ?TranslatorService $__translatorService = null;

    abstract public function getBeanFactory(): BeanFactory;

    public function getTranslatorService(): TranslatorService
    {
        if ($this->__translatorService === null) {
            $translatorService = $this->getBeanFactory()->get('TranslatorService');
            assert($translatorService instanceof TranslatorService);
            $this->setTranslatorService($translatorService);
        }
        assert($this->__translatorService !== null);

        return $this->__translatorService;
    }

    public function setTranslatorService(TranslatorService $translatorService): void
    {
        $this->__translatorService = $translatorService;
    }
}
