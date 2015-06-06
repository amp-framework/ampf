<?php

namespace ampf\database\factories;

interface Factory
{
	/**
	 * @return \PDO
	 */
	public function getPDO();
}
