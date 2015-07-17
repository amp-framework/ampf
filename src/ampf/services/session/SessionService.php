<?php

namespace ampf\services\session;

interface SessionService
{
	/**
	 * @return void
	 */
	public function destroy();

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function getAttribute($key);

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasAttribute($key);

	/**
	 * @param string $key
	 * @return void
	 */
	public function removeAttribute($key);

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 * @throws \Exception
	 */
	public function setAttribute($key, $value);
}
