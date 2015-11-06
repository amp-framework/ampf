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

	/**
	 * @param string $json
	 * @return array
	 */
	static public function decodeJSONArray($json)
	{
		$array = json_decode($json);
		if ($array === null || !is_array($array) || count($array) < 1)
		{
			return array();
		}
		return $array;
	}
}
