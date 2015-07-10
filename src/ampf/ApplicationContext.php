<?php

namespace ampf;

class ApplicationContext
{
	static public function boot($configFiles = null)
	{
		$config = array();
		if ($configFiles != null)
		{
			foreach($configFiles as $configFile)
			{
				$config = self::mergeConfig($config, require($configFile));
			}
		}
		return $config;
	}

	static protected function mergeConfig($config1, $config2)
	{
		$result = array();
		foreach ($config1 as $key => $value)
		{
			if (!isset($config2[$key]))
			{
				$result[$key] = $value;
			}
			else
			{
				if (is_array($value))
				{
					$result[$key] = self::mergeConfig($value, $config2[$key]);
				}
				else
				{
					$result[$key] = $config2[$key];
				}
			}
		}
		foreach ($config2 as $key => $value)
		{
			if (!isset($result[$key]))
			{
				$result[$key] = $value;
			}
		}
		return $result;
	}
}
