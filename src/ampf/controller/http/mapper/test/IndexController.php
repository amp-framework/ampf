<?php

namespace ampf\controller\http\mapper\test;

use ampf\controller\http\AbstractController;

class IndexController extends AbstractController
{
	use \ampf\beans\access\TestRepositoryAccess;

	public function execute()
	{
		$this->uniqueActionID = 'mappertest_index';

		$testRepo = $this->getTestRepository();
		if ($this->getRequest()->hasGetParam('add') && $this->getRequest()->hasCorrectToken())
		{
			$test = $testRepo->create();
			$test->setEntry(md5(time()));

			$testRepo->persist($test);
			$testRepo->flush();

			$this->getRequest()->setRedirect('mapper/test/index');
			return;
		}

		$this->getView()->set('allEntries', $testRepo->findAll());

		$this->getRequest()->setResponse(
			$this->getView()->render('http/mapper/test/index/default.html.php')
		);
	}
}
