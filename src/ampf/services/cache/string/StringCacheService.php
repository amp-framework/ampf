<?php

namespace ampf\services\cache\string;

interface StringCacheService
{
	/**
	 * @param string $key
	 * @return mixed False or the cache contents
	 */
	public function get($key);

	/**
	 * @param string $key
	 * @param string $string
	 * @param integer $ttl
	 * @return boolean
	 * @throws \Exception
	 */
	public function set($key, $string, $ttl = null);
}
