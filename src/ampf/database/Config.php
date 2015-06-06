<?php

namespace ampf\database;

interface Config
{
	/**
	 * @return string
	 */
	public function getDsn();

	/**
	 * @return string
	 */
	public function getPassword();

	/**
	 * @return string
	 */
	public function getUsername();
}
