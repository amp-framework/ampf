<?php

namespace ampf\services\session;

interface SessionService
{
	public function setAttribute($key, $value);

	public function hasAttribute($key);

	public function getAttribute($key);

	public function removeAttribute($key);

	public function destroy();
}
