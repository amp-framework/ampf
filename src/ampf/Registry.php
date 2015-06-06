<?php

namespace ampf;

class Registry
{
	static protected $_memory = array();

	static public function set($key, $value)
	{
		self::$_memory[$key] = $value;
	}

	static public function get($key)
	{
		if (!self::has($key)) return null;
		return self::$_memory[$key];
	}

	static public function has($key)
	{
		return isset(self::$_memory[$key]);
	}
}
