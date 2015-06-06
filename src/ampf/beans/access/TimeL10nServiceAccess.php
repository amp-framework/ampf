<?php

namespace ampf\beans\access;

use ampf\services\timel10n\TimeL10nService;

trait TimeL10nServiceAccess
{
	protected $__timeL10nService = null;

	public function getTimeL10nService()
	{
		if ($this->__timeL10nService === null)
		{
			$this->setTimeL10nService($this->getBeanFactory()->get('TimeL10nService'));
		}
		return $this->__timeL10nService;
	}

	public function setTimeL10nService(TimeL10nService $timeL10nService)
	{
		$this->__timeL10nService = $timeL10nService;
	}
}
