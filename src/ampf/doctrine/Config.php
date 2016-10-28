<?php

namespace ampf\doctrine;

interface Config
{
	/**
	 * @return \Doctrine\ORM\Configuration
	 */
	public function getConfiguration();

	/**
	 * @return array
	 */
	public function getConnectionParams();

	/**
	 * @return array
	 */
	public function getMappingOverrides();

	/**
	 * @return array
	 */
	public function getTypeOverrides();
}
