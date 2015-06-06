<?php

namespace ampf\beans;

class DefaultBeanFactory implements BeanFactory
{
	protected $memory = null;
	protected $statistics = array(
		'beansCreated' => 0,
	);

	public function __construct($config)
	{
		$this->memory['BeanFactory'] = $this;
		$this->memory['Config'] = $config;
	}

	public function get($beanID)
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

	public function set($beanID, $object)
	{
		$this->memory[$beanID] = $object;
	}

	public function has($beanID)
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

	public function is($object, $beanID)
	{
		if (!is_object($object)) return false;

		$config = $this->get('Config');
		if (!isset($config['beans'][$beanID])) return false;

		$class = $config['beans'][$beanID]['class'];

		if (!class_exists($class)) return false;

		return ($object instanceof $class);
	}

	public function getStatistics()
	{
		return $this->statistics;
	}

	protected function evalConfig($beanID, $bean, $beanConfig)
	{
		$this->evalConfigParent($beanID, $bean, $beanConfig);
		$this->evalConfigProperties($beanID, $bean, $beanConfig);
		$this->evalConfigInitMethod($beanID, $bean, $beanConfig);
		$this->evalConfigScope($beanID, $bean, $beanConfig);
	}

	protected function evalConfigParent($beanID, $bean, $beanConfig)
	{
		if (isset($beanConfig['parent']))
		{
			$parent = $beanConfig['parent'];
			$config = $this->get('Config');
			$parentConfig = $config['beans'][$parent];
			$this->evalConfig(null, $bean, $parentConfig);
		}
	}

	protected function evalConfigProperties($beanID, $bean, $beanConfig)
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
	}

	protected function evalConfigInitMethod($beanID, $bean, $beanConfig)
	{
		if (isset($beanConfig['initMethod']))
		{
			$initMethod = $beanConfig['initMethod'];
			$bean->{$initMethod}();
		}
	}

	protected function evalConfigScope($beanID, $bean, $beanConfig)
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
	}
}
