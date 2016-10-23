<?php

namespace ampf\beans;

interface BeanFactory
{
	/**
	 * @param string $beanID
	 * @return object
	 * @throws \Exception
	 */
	public function get($beanID);

	/**
	 * @param string $beanID
	 * @param object $object
	 * @return BeanFactory
	 */
	public function set($beanID, $object);

	/**
	 * @param string $beanID
	 * @return boolean
	 */
	public function has($beanID);

	/**
	 * @param object $object
	 * @param string $beanID
	 * @return boolean
	 */
	public function is($object, $beanID);

	/**
	 * @return array
	 */
	public function getStatistics();
}
