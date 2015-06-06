<?php

namespace ampf\beans\access;

use ampf\services\translator\TranslatorService;

trait TranslatorServiceAccess
{
	protected $__translatorService = null;

	public function getTranslatorService()
	{
		if ($this->__translatorService === null)
		{
			$this->setTranslatorService($this->getBeanFactory()->get('TranslatorService'));
		}
		return $this->__translatorService;
	}

	public function setTranslatorService(TranslatorService $translatorService)
	{
		$this->__translatorService = $translatorService;
	}
}
