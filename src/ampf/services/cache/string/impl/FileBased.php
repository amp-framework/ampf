<?php

namespace ampf\services\cache\string\impl;

use ampf\services\cache\string\StringCacheService;

class FileBased implements StringCacheService
{
	protected $cacheDir = null;
	protected $defaultTTL = null;

	/**
	 * @param string $key
	 * @return mixed False or the cache contents
	 */
	public function get($key)
	{
		$path = $this->getPath($key);
		if (!file_exists($path))
		{
			return false;
		}

		$content = file_get_contents($path);
		if (trim($content) == '')
		{
			unlink($path);
			return false;
		}

		$json = json_decode($content);
		if ($json === null || !is_object($json))
		{
			unlink($path);
			return false;
		}
		if (!isset($json->until) || !isset($json->string))
		{
			unlink($path);
			return false;
		}
		if ($json->until < time())
		{
			unlink($path);
			return false;
		}

		return $json->string;
	}

	/**
	 * @param string $key
	 * @param string $string
	 * @param integer $ttl
	 * @return boolean
	 * @throws \Exception
	 */
	public function set($key, $string, $ttl = null)
	{
		if (trim($string) == '')
		{
			throw new \Exception();
		}
		if ($ttl === null)
		{
			$ttl = $this->defaultTTL;
		}
		if (((int)$ttl) != $ttl || $ttl < 1)
		{
			throw new \Exception();
		}

		$json = new \stdClass();
		$json->until = (time() + $ttl);
		$json->string = $string;

		$content = json_encode($json);

		$path = $this->getPath($key);
		file_put_contents($path, $content);

		return true;
	}

	/**
	 * Protected methods
	 */

	protected function getPath($key)
	{
		if (!$this->isCorrectKey($key))
		{
			throw new \Exception();
		}
		return ($this->cacheDir . '/' . $key . '.asc');
	}

	protected function isCorrectKey($key)
	{
		return preg_match('/^[a-zA-Z0-9_\-\.]+$/', $key);
	}

	/**
	 * Bean setters
	 */

	public function setConfig($config)
	{
		if (!is_array($config) || count($config) < 1) throw new \Exception();

		if (!isset($config['stringfilecache'])) throw new \Exception();
		if (!is_array($config['stringfilecache'])) throw new \Exception();

		if (!isset($config['stringfilecache']['cachedir'])) throw new \Exception();

		$cachedir = realpath($config['stringfilecache']['cachedir']);
		if (
			$cachedir === false
			|| !is_dir($cachedir)
			|| !is_writable($cachedir)
		) throw new \Exception();

		$this->cacheDir = $cachedir;

		$this->defaultTTL = 3600;
		if (isset($config['stringfilecache']['defaultttl']))
		{
			$this->defaultTTL = ((int)$config['stringfilecache']['defaultttl']);
		}
	}
}
