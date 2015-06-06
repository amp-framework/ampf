<?php

namespace ampf\beans;

interface BeanFactory
{
	public function get($beanID);

	public function set($beanID, $object);

	public function has($beanID);

	public function is($object, $beanID);

	public function getStatistics();
}
