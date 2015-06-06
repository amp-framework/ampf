<?php

namespace ampf\services\timel10n\impl;

use ampf\services\timel10n\TimeL10nService;

class DefaultTimeL10nService implements TimeL10nService
{
	public function getUtcDatetime($unixtime = null)
	{
		if ($unixtime === null) $unixtime = time();
		if (!is_scalar($unixtime) || trim($unixtime) == '') throw new \Exception();

		$date = new \DateTime();
		$date->setTimestamp($unixtime);
		$date->setTimezone(new \DateTimeZone('UTC'));
		return $date->format('Y-m-d H:i:s');
	}

	public function getUnixtimeByUtcDatetime($datetime)
	{
		if (!is_scalar($datetime) || trim($datetime) == '') throw new \Exception();
		$date = new \DateTime($datetime, new \DateTimeZone('UTC'));
		return $date->format('U');
	}
}
