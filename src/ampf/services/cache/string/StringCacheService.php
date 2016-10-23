<?php

namespace ampf\services\cache\string;

interface StringCacheService
{
	/**
	 * @param string $key
	 * @return mixed False or the cache contents
	 */
	public function get(string $key);

	/**
	 * @param string $key
	 * @param string $string
	 * @param integer $ttl
	 * @return boolean
	 * @throws \Exception
	 */
	public function set(string $key, string $string, int $ttl = null);
}
