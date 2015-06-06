<?php

namespace ampf\controller\http;

class IndexController extends AbstractController
{
	public function execute()
	{
		$this->uniqueActionID = 'index_index';

		$this->getRequest()->setResponse(
			$this->getView()->render('http/index/default.html.php')
		);
	}
}
