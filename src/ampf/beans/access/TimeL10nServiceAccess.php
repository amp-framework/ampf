<?php

namespace ampf\beans\access;

use ampf\services\timel10n\TimeL10nService;

trait TimeL10nServiceAccess
{
	protected $__timeL10nService = null;

	/**
	 * @return TimeL10nService
	 */
	public function getTimeL10nService()
	{
		if ($this->__timeL10nService === null)
		{
			$this->setTimeL10nService($this->getBeanFactory()->get('TimeL10nService'));
		}
		return $this->__timeL10nService;
	}

	/**
	 * @param TimeL10nService $timeL10nService
	 */
	public function setTimeL10nService(TimeL10nService $timeL10nService)
	{
		$this->__timeL10nService = $timeL10nService;
	}

	/**
	 * @return \ampf\beans\BeanFactory
	 */
	abstract public function getBeanFactory();
}
