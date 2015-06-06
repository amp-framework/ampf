<?php

namespace ampf\views;

interface View
{
	public function set($key, $value);

	public function render($viewID);

	public function reset();

	public function subRender($viewID, $params = null);

	public function subRoute($controllerBean, $params = null);

	public function escape($string);

	public function formatNumber($number, $decimals = null, $decPoint = null, $thousandsSep = null);

	public function formatTime($time = null, $format = null);
}
