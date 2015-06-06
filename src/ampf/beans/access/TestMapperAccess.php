<?php

namespace ampf\beans\access;

use ampf\database\mapper\TestMapper;

trait TestMapperAccess
{
	protected $__testMapper = null;

	public function getTestMapper()
	{
		if ($this->__testMapper === null)
		{
			$this->setTestMapper($this->getBeanFactory()->get('TestMapper'));
		}
		return $this->__testMapper;
	}

	public function setTestMapper(TestMapper $testMapper)
	{
		$this->__testMapper = $testMapper;
	}
}
