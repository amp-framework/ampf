<?php

namespace ampf\controller\http;

class IndexRedirectController extends AbstractController
{
	public function execute($pathInfo = null)
	{
		$this->getRequest()->setRedirect('index', null, '303');
	}

	/**
	 * Override to not render layout etc.
	 */
	public function beforeAction()
	{
	}

	/**
	 * Override to not render layout etc.
	 */
	public function afterAction()
	{
	}
}
