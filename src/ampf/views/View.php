<?php

namespace ampf\views;

interface View
{
	public function get($key);

	public function set($key, $value);

	public function render($viewID);

	public function reset();

	public function subRender($viewID, $params = null);

	public function subRoute($controllerBean, $params = null);

	public function escape($string);

	public function formatNumber($number, $decimals = null, $decPoint = null, $thousandsSep = null);

	/**
	 * @param \DateTime|numeric|null $time Either a \DateTime instance or a numeric representing an UNIX timestamp
	 * @param string $format A \DateTime::format compatible string
	 */
	public function formatTime($time = null, string $format = null);
}
