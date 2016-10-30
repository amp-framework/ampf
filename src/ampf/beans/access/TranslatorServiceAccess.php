<?php

namespace ampf\beans\access;

use ampf\services\translator\TranslatorService;

trait TranslatorServiceAccess
{
	protected $__translatorService = null;

	/**
	 * @return TranslatorService
	 */
	public function getTranslatorService()
	{
		if ($this->__translatorService === null)
		{
			$this->setTranslatorService($this->getBeanFactory()->get('TranslatorService'));
		}
		return $this->__translatorService;
	}

	/**
	 * @param TranslatorService $translatorService
	 */
	public function setTranslatorService(TranslatorService $translatorService)
	{
		$this->__translatorService = $translatorService;
	}

	/**
	 * @return \ampf\beans\BeanFactory
	 */
	abstract public function getBeanFactory();
}
