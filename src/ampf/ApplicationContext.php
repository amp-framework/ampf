<?php

namespace ampf;

class ApplicationContext
{
	static public function boot($baseSrcDirs, $configFiles = null)
	{
		if (!is_array($baseSrcDirs) || count($baseSrcDirs) == 0) throw new \Exception();
		foreach ($baseSrcDirs as $dir)
		{
			if (!is_dir($dir))
			{
				throw new \Exception();
			}
		}

		// set up autoloader
		spl_autoload_register(function($class) use ($baseSrcDirs) {
			$classArray = explode('\\', $class);
			foreach ($classArray as $value)
			{
				if (!preg_match('/^[A-Za-z0-9]+$/', $value)) return;
			}
			$finalPath = null;
			foreach ($baseSrcDirs as $dir)
			{
				$path = ($dir . implode('/', $classArray) . '.php');
				if (file_exists($path))
				{
					$finalPath = $path;
					break;
				}
			}
			if (is_null($finalPath)) return;

			ob_start();
			require_once($path);
			ob_end_clean();
		});

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
