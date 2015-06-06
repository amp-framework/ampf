<?php

namespace ampf\controller\http\Subroute;

use ampf\controller\http\AbstractController;

class ExampleController extends AbstractController
{
	public function execute()
	{
		$this->getRequest()->setResponse(
			'Subroute example.'
		);
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
