<?php

namespace ampf;

class ApplicationContext
{
	/**
	 * @param array $configFiles
	 * @return array
	 */
	static public function boot(array $configFiles = null)
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

	/**
	 * @param array $config1
	 * @param array $config2
	 * @param int $depth
	 * @return array
	 */
	static protected function mergeConfig(array $config1, array $config2, int $depth = 0)
	{
		$result = array();
		foreach ($config1 as $key => $value)
		{
			// If config2 has no such entry, just take entry from config1
			if (!isset($config2[$key]))
			{
				$result[$key] = $value;
				unset($config2[$key]);
			}
			else
			{
				// If the value is an array, recurse one level deep
				if (is_array($value) && $depth == 0)
				{
					$result[$key] = static::mergeConfig($value, $config2[$key], ($depth+1));
				}
				// Else just take over the value from config2
				else
				{
					$result[$key] = $config2[$key];
				}
				unset($config2[$key]);
			}
		}
		// Copy all remaining entries from config2 to config1
		foreach ($config2 as $key => $value)
		{
			$result[$key] = $value;
		}
		return $result;
	}
}
