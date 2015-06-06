<?php

namespace ampf\controller;

interface Controller
{
	public function beforeAction();

	public function afterAction();

	public function execute();
}
