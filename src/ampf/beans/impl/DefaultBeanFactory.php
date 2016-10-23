<?php

namespace ampf\beans\impl;

use \ampf\beans\BeanFactory;
use \ampf\beans\BeanFactoryAccess;

class DefaultBeanFactory implements BeanFactory
{
	/**
	 * @var array
	 */
	protected $memory = array();

	/**
	 * @var array
	 */
	protected $statistics = array(
		'beansCreated' => 0,
	);

	public function __construct(array $config)
	{
		$this->memory['BeanFactory'] = $this;
		$this->memory['Config'] = $config;
	}

	/**
	 * @param string $beanID
	 * @return object
	 * @throws \Exception
	 */
	public function get(string $beanID)
	{
		if (isset($this->memory[$beanID]))
		{
			$bean = $this->memory[$beanID];
		}
		else
		{
			$config = $this->get('Config');
			if (!isset($config['beans'][$beanID]))
			{
				throw new \Exception("No configuration for bean {$beanID} found");
			}

			$beanConfig = $config['beans'][$beanID];
			$class = $beanConfig['class'];
			$bean = new $class();

			$this->evalConfig($beanID, $bean, $beanConfig);

			$this->statistics['beansCreated']++;
		}
		return $bean;
	}

	/**
	 * @param string $beanID
	 * @param object $object
	 * @return BeanFactory
	 */
	public function set(string $beanID, $object)
	{
		$this->memory[$beanID] = $object;
		return $this;
	}

	/**
	 * @param string $beanID
	 * @return boolean
	 */
	public function has(string $beanID)
	{
		if (isset($this->memory[$beanID]))
		{
			return true;
		}
		$config = $this->get('Config');
		if (isset($config['beans'][$beanID]))
		{
			return true;
		}
		return false;
	}

	/**
	 * @param object $object
	 * @param string $beanID
	 * @return boolean
	 */
	public function is($object, string $beanID)
	{
		if (!is_object($object)) return false;

		$config = $this->get('Config');
		if (!isset($config['beans'][$beanID])) return false;

		$class = $config['beans'][$beanID]['class'];

		if (!class_exists($class)) return false;

		return ($object instanceof $class);
	}

	/**
	 * @return array
	 */
	public function getStatistics()
	{
		return $this->statistics;
	}

	/**
	 * @param string $beanID
	 * @param object $bean
	 * @param array $beanConfig
	 * @return DefaultBeanFactory
	 */
	protected function evalConfig(string $beanID, $bean, array $beanConfig)
	{
		return $this
			->evalConfigParent($beanID, $bean, $beanConfig)
			->evalConfigProperties($beanID, $bean, $beanConfig)
			->evalConfigInitMethod($beanID, $bean, $beanConfig)
			->evalConfigScope($beanID, $bean, $beanConfig);
	}

	/**
	 * @param string $beanID
	 * @param object $bean
	 * @param array $beanConfig
	 * @return DefaultBeanFactory
	 */
	protected function evalConfigParent(string $beanID, $bean, array $beanConfig)
	{
		if (isset($beanConfig['parent']))
		{
			$parent = $beanConfig['parent'];
			$config = $this->get('Config');
			$parentConfig = $config['beans'][$parent];
			$this->evalConfig(null, $bean, $parentConfig);
		}
		return $this;
	}

	/**
	 * @param string $beanID
	 * @param object $bean
	 * @param array $beanConfig
	 * @return DefaultBeanFactory
	 * @throws \Exception
	 */
	protected function evalConfigProperties(string $beanID, $bean, array $beanConfig)
	{
		if (isset($beanConfig['properties']))
		{
			foreach ($beanConfig['properties'] as $bean2ID => $field)
			{
				$setter = ('set' . ucfirst($field));
				if (method_exists($bean, $setter))
				{
					$bean->{$setter}($this->get($bean2ID));
				}
				else
				{
					throw new \Exception("Property {$field} can not be set due to missing setter");
				}
			}
		}

		// Inject us, if wanted. This is done through an interface and not through
		// the normal properties configuration, as this is needed way too much.
		if ($bean instanceof BeanFactoryAccess)
		{
			$bean->setBeanFactory($this);
		}

		return $this;
	}

	/**
	 * @param string $beanID
	 * @param object $bean
	 * @param array $beanConfig
	 * @return DefaultBeanFactory
	 */
	protected function evalConfigInitMethod(string $beanID, $bean, array $beanConfig)
	{
		if (isset($beanConfig['initMethod']))
		{
			$initMethod = $beanConfig['initMethod'];
			$bean->{$initMethod}();
		}
		return $this;
	}

	/**
	 * @param string $beanID
	 * @param object $bean
	 * @param array $beanConfig
	 * @return DefaultBeanFactory
	 */
	protected function evalConfigScope(string $beanID, $bean, array $beanConfig)
	{
		if ($beanID == null) return;

		$scope = 'singleton';
		if (isset($beanConfig['scope'])) $scope = $beanConfig['scope'];

		if ($scope == 'prototype')
		{
			// do nothing
		}
		else
		{
			// handle as if scope == 'singleton'
			$this->memory[$beanID] = $bean;
		}

		return $this;
	}
}
