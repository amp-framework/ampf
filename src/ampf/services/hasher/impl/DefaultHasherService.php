<?php

namespace ampf\services\hasher\impl;

use ampf\services\hasher\HasherService;

class DefaultHasherService implements HasherService
{
	static protected $TOKEN_TIMING_ATT = '$2y$12$7bXzdUEuvvooZkWPLBbTCux4VdVOJfTv2uLCS2ysoHhDOgVFRE3Q2';

	/**
	 * @param string $input
	 * @return void
	 * @throws \Exception
	 */
	public function avoidTimingAttack(string $input)
	{
		// Burn some CPU time by doing an useless check
		$this->check($input, static::$TOKEN_TIMING_ATT);
	}

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

		// Randomly sleep some milliseconds
		$this->sleep();

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

		// Randomly sleep some milliseconds
		$this->sleep();

		$hash = password_hash($string, \PASSWORD_BCRYPT, array('cost' => '12'));
		if (strlen($hash) != 60) throw new \Exception();

		return $hash;
	}

	/**
	 * Sleeps randomly between 1 and 5 milliseconds to avoid timing attacks
	 * and to mask the real runtime of the HasherService.
	 */
	protected function sleep()
	{
		usleep(mt_rand(
			(1 * 1000),
			(5 * 1000)
		));
	}
}
