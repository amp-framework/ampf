<?php

namespace ampf\controller\http;

use ampf\controller\Controller;
use ampf\requests\HttpRequest;
use ampf\views\HttpView;

abstract class AbstractController implements Controller
{
	use \ampf\beans\access\BeanFactoryAccess;

	protected $_request = null;
	protected $_view = null;

	protected $uniqueActionID = null;

	public function beforeAction()
	{
	}

	public function afterAction()
	{
		if ($this->getRequest()->isRedirect())
		{
			return;
		}

		if (is_null($this->uniqueActionID))
		{
			throw new \Exception('uniqueActionID not set.');
		}

		// reset the view
		$this->getView()->reset();
		// set the response until now into the resetted view
		$this->getView()->set('content', $this->getRequest()->getResponse());
		// set the unique action id into the view
		$this->getView()->set('uniqueActionID', $this->uniqueActionID);
		// get the new response
		$newResponse = $this->getView()->render('http/layouts/default.html.php');
		// and, finally, set it into the request object
		$this->getRequest()->setResponse($newResponse);
	}

	// Bean getters

	/**
	 * @return \ampf\requests\HttpRequest
	 */
	public function getRequest()
	{
		if (is_null($this->_request))
		{
			$this->setRequest($this->getBeanFactory()->get('Request'));
		}
		return $this->_request;
	}

	/**
	 * @return \ampf\views\HttpView
	 */
	public function getView()
	{
		if (is_null($this->_view))
		{
			$this->setView($this->getBeanFactory()->get('View'));
		}
		return $this->_view;
	}

	// Bean setters

	public function setRequest(HttpRequest $request)
	{
		$this->_request = $request;
	}

	public function setView(HttpView $view)
	{
		$this->_view = $view;
	}
}
