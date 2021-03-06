<?php

namespace ampf\services\hasher;

interface HasherService
{
	/**
	 * @param string $input
	 * @return void
	 * @throws \Exception
	 */
	public function avoidTimingAttack(string $input);

	/**
	 * @param string $string
	 * @param string $storedHash
	 * @return boolean
	 * @throws \Exception
	 */
	public function check(string $string, string $storedHash);

	/**
	 * @param string $string
	 * @return string
	 * @throws \Exception
	 */
	public function hash(string $string);
}
