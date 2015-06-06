<?php

namespace ampf\beans\access;

use ampf\services\xsrfToken\XsrfTokenService;

trait XsrfTokenServiceAccess
{
	protected $__xsrfTokenService = null;

	public function getXsrfTokenService()
	{
		if ($this->__xsrfTokenService === null)
		{
			$this->setXsrfTokenService($this->getBeanFactory()->get('XsrfTokenService'));
		}
		return $this->__xsrfTokenService;
	}

	public function setXsrfTokenService(XsrfTokenService $xsrfTokenService)
	{
		$this->__xsrfTokenService = $xsrfTokenService;
	}
}
