<?php

namespace ampf\services\hasher\impl;

use ampf\services\hasher\HasherService;

class DefaultHasherService implements HasherService
{
	public function hash($string)
	{
		if (!is_string($string) || trim($string) == '') throw new \Exception();

		$hash = password_hash($string, \PASSWORD_BCRYPT, array('cost' => '12'));
		if (strlen($hash) != 60) throw new \Exception();

		return $hash;
	}

	public function check($string, $storedHash)
	{
		if (!is_string($string) || trim($string) == '') throw new \Exception('String to check needs to be not-blank.');
		if (!is_string($storedHash) || strlen($storedHash) != 60) throw new \Exception('No valid bcrypt hash given.');

		return password_verify($string, $storedHash);
	}
}
