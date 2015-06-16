<?php

namespace ampf\database\models;

abstract class AbstractModel implements Model
{
	protected static $_ID_KEY = "ID";

	protected $_initialized = false;
	protected $_properties = array();
	protected $_virtualProperties = array();
	protected $_methods = array();

	public function equals(AbstractModel $model)
	{
		// First check whether we are the same instance of a model
		$myClass = get_class($this);
		$hisClass = get_class($model);
		if ($myClass !== $hisClass) return false;

		// I have not been saved yet - I cannot be the same as another object
		if ($this->{self::$_ID_KEY} === null) return false;

		// Finally compare our IDs, if they are the same, we are the same
		return ($this->{self::$_ID_KEY} === $model->{self::$_ID_KEY});
	}

	public function fillByStdClass($obj)
	{
		if ($this->_initialized) throw new \Exception();
		$this->update($obj, true);
		$this->_initialized = true;
	}

	public function getAllProperties()
	{
		$result = array();
		foreach (array_keys($this->_properties) as $property)
		{
			$result[$property] = $this->{$property};
		}
		return $result;
	}

	/**
	 * @param stdclass $obj
	 * @param boolean overwrite_id
	 * @throws \Exception
	 */
	public function update($obj, $overwrite_id = false)
	{
		if (!$overwrite_id && isset($obj->{self::$_ID_KEY}))
		{
			throw new \Exception();
		}

		// Fill the normal properties
		foreach (array_keys($this->_properties) as $property)
		{
			if (isset($obj->{$property}))
			{
				$this->__set($property, $obj->{$property});
			}
		}
		// Fill the virtual properties
		foreach (array_keys($this->_virtualProperties) as $property)
		{
			if (isset($obj->{$property}))
			{
				$this->{$property} = $obj->{$property};
			}
		}
	}

	// Magic methods

	public function __call($method, $params = null)
	{
		if (is_null($params))
		{
			$params = array();
		}

		if (strpos($method, 'get') === 0)
		{
			$property = lcfirst(substr($method, 3));
			// special handling for ID
			if (strtolower($property) === strtolower(self::$_ID_KEY))
			{
				$property = self::$_ID_KEY;
			}
			return $this->__get($property);
		}
		if (strpos($method, 'set') === 0)
		{
			$property = lcfirst(substr($method, 3));
			// special handling for ID
			if (strtolower($property) === strtolower(self::$_ID_KEY))
			{
				$property = self::$_ID_KEY;
			}
			return $this->__set($property, reset($params));
		}

		throw new \Exception();
	}

	public function __construct()
	{
		foreach (array_keys(get_object_vars($this)) as $property)
		{
			if (strpos($property, '_') !== 0)
			{
				$this->_properties[$property] = true;
			}
			else
			{
				$this->_virtualProperties[$property] = true;
			}
		}
		foreach (get_class_methods($this) as $method)
		{
			$this->_methods[$method] = true;
		}
	}

	public function __get($key)
	{
		$getter = ('get' . ucfirst($key));
		if (isset($this->_methods[$getter]))
		{
			return $this->{$getter}();
		}

		if (isset($this->_virtualProperties[$key]))
		{
			return $this->{$key};
		}
		if (!isset($this->_properties[$key]))
		{
			throw new \Exception("Property {$key} not found in this model.");
		}
		return $this->{$key};
	}

	public function __set($key, $value)
	{
		$setter = ('set' . ucfirst($key));
		if (isset($this->_methods[$setter]))
		{
			$this->{$setter}($value);
			return;
		}

		if (!isset($this->_properties[$key]))
		{
			throw new \Exception("Property {$key} not found in this model.");
		}
		$this->{$key} = $value;
	}
}
