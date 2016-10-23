<?php

namespace ampf\beans;

interface BeanFactory
{
	/**
	 * @param string $beanID
	 * @return object
	 * @throws \Exception
	 */
	public function get(string $beanID);

	/**
	 * @param string $beanID
	 * @param object $object
	 * @return BeanFactory
	 */
	public function set(string $beanID, $object);

	/**
	 * @param string $beanID
	 * @return boolean
	 */
	public function has(string $beanID);

	/**
	 * @param object $object
	 * @param string $beanID
	 * @return boolean
	 */
	public function is($object, string $beanID);

	/**
	 * @return array
	 */
	public function getStatistics();
}
