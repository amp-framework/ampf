<?php

namespace ampf\controller\http\mapper\test;

use ampf\controller\http\AbstractController;

class IndexController extends AbstractController
{
	use \ampf\beans\access\TestMapperAccess;

	public function execute()
	{
		$this->uniqueActionID = 'mappertest_index';

		$allEntries = $this->getTestMapper()->getAllEntries();

		$this->getView()->set('allEntries', $allEntries);

		$this->getRequest()->setResponse(
			$this->getView()->render('http/mapper/test/index/default.html.php')
		);
	}
}
