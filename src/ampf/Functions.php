<?php

namespace ampf;

abstract class Functions
{
	/**
	 * @param string $str
	 * @param int $l
	 * @return string[]
	 */
	static public function mb_str_split($str, $l = 0)
	{
		if ($l > 0)
		{
			$ret = array();
			$len = mb_strlen($str);
			for ($i = 0; $i < $len; $i += $l) {
				$ret[] = mb_substr($str, $i, $l);
			}
			return $ret;
		}
		return preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
	}

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
