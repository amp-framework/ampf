<?php

namespace ampf\services\hasher\impl;

use ampf\services\hasher\HasherService;

class DefaultHasherService implements HasherService
{
	/**
	 * @param string $string
	 * @param string $storedHash
	 * @return boolean
	 * @throws \Exception
	 */
	public function check(string $string, string $storedHash)
	{
		if (trim($string) == '') throw new \Exception('String to check needs to be not-blank.');
		if (strlen($storedHash) != 60) throw new \Exception('No valid bcrypt hash given.');

		return password_verify($string, $storedHash);
	}

	/**
	 * @param string $string
	 * @return string
	 * @throws \Exception
	 */
	public function hash(string $string)
	{
		if (trim($string) == '') throw new \Exception();

		$hash = password_hash($string, \PASSWORD_BCRYPT, array('cost' => '12'));
		if (strlen($hash) != 60) throw new \Exception();

		return $hash;
	}
}
