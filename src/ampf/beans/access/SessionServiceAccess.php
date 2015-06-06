<?php

namespace ampf\beans\access;

use ampf\services\session\SessionService;

trait SessionServiceAccess
{
	protected $__sessionService = null;

	/**
	 * @return SessionService
	 */
	public function getSessionService()
	{
		if ($this->__sessionService === null)
		{
			$this->setSessionService($this->getBeanFactory()->get('SessionService'));
		}
		return $this->__sessionService;
	}

	public function setSessionService(SessionService $sessionService)
	{
		$this->__sessionService = $sessionService;
	}
}
