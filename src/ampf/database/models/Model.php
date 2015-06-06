<?php

namespace ampf\database\models;

interface Model
{
	public function fillByStdClass($obj);

	public function getAllProperties();
}
