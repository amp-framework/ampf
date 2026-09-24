<?php

declare(strict_types=1);

namespace ampf\BeanAccess\Service;

use ampf\BeanAccess\AbstractAccess;
use ampf\Service\Translator\TranslatorServiceInterface;

trait TranslatorServiceAccess
{
    use AbstractAccess;

    protected ?TranslatorServiceInterface $__translatorService = null;

    public function getTranslatorService(): TranslatorServiceInterface
    {
        if ($this->__translatorService === null) {
            $object = $this->getBeanFactory()->get(TranslatorServiceInterface::class);
            assert($object instanceof TranslatorServiceInterface);
            $this->setTranslatorService($object);
        }

        assert($this->__translatorService instanceof TranslatorServiceInterface);

        return $this->__translatorService;
    }

    public function setTranslatorService(TranslatorServiceInterface $object): void
    {
        $this->__translatorService = $object;
    }
}
