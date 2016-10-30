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

	/**
	 * @param SessionService $sessionService
	 */
	public function setSessionService(SessionService $sessionService)
	{
		$this->__sessionService = $sessionService;
	}

	/**
	 * @return \ampf\beans\BeanFactory
	 */
	abstract public function getBeanFactory();
}
