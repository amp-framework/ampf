<?php

namespace ampf\controller\cli\help;

use ampf\controller\cli\AbstractController;

class IndexController extends AbstractController
{
	public function execute()
	{
		$this->getRequest()->setResponse(
			$this->getView()->render('cli/help/index/default.txt.php')
		);
	}
}
