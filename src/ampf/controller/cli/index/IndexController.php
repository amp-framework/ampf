<?php

namespace ampf\controller\cli\index;

use ampf\controller\cli\AbstractController;

class IndexController extends AbstractController
{
	public function execute()
	{
		$this->getRequest()->setResponse(
			$this->getView()->render('cli/index/index/default.txt.php')
		);
	}
}
