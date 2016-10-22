<?php

namespace ampf\doctrine;

interface Config
{
	/**
	 * @return string
	 */
	public function getCacheDir();

	/**
	 * @return array
	 */
	public function getConnectionParams();

	/**
	 * @return array
	 */
	public function getEntities();

	/**
	 * @return string
	 */
	public function getProxyDir();

	/**
	 * @return boolean
	 */
	public function isDevMode();

	/**
	 * @return boolean
	 */
	public function useSimpleAnnotationReader();
}
