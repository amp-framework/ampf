<?php

namespace ampf\views;

interface ViewResolver
{
	public function getViewFilename($view);
}
