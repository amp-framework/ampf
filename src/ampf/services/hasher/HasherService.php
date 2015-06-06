<?php

namespace ampf\services\hasher;

interface HasherService
{
	public function hash($string);

	public function check($string, $storedHash);
}
