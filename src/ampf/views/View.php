<?php

namespace ampf\views;

interface View
{
	/**
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get(string $key, $default = null);

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function set(string $key, $value);

	/**
	 * @param string $key
	 * @return bool
	 */
	public function has(string $key);

	/**
	 * @param string $view
	 * @return string
	 */
	public function render(string $view);

	public function reset();

	/**
	 * @param string $viewID
	 * @param array $params
	 * @return string
	 */
	public function subRender(string $viewID, array $params = null);

	/**
	 * @param numeric $number
	 * @param int $decimals
	 * @param string $decPoint
	 * @param string $thousandsSep
	 * @return string
	 */
	public function formatNumber($number, int $decimals = null, string $decPoint = null, string $thousandsSep = null);

	/**
	 * @param \DateTime|numeric|null $time Either a \DateTime instance or a numeric representing an UNIX timestamp
	 * @param string $format A \DateTime::format compatible string
	 * @return string
	 * @throws \Exception
	 */
	public function formatTime($time = null, string $format = null);

	/**
	 * @param string $key
	 * @param array $args
	 * @return string
	 */
	public function t(string $key, array $args = null);

	public function subRoute($controllerBean, $params = null);

	public function escape($string);
}
