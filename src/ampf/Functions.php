<?php

namespace ampf;

abstract class Functions
{
	/**
	 * @param string $string
	 * @return string
	 * @throws \Exception
	 */
	static public function mb_ucfirst($string)
	{
		if (!is_string($string))
		{
			throw new \Exception();
		}
		if (trim($string) == '')
		{
			return $string;
		}

		$first = mb_strtoupper(mb_substr($string, 0, 1));
		return ($first . mb_substr($string, 1));
	}
}
