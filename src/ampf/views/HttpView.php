<?php

namespace ampf\views;

interface HttpView extends View
{
	public function getAssetLink($relativeLink);

	public function getActionLink($routeID, $params = null, $addToken = false);
}
