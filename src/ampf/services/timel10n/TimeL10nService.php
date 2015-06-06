<?php

namespace ampf\services\timel10n;

interface TimeL10nService
{
	public function getUtcDatetime($unixtime = null);

	public function getUnixtimeByUtcDatetime($datetime);
}
