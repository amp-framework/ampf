<?php

namespace ampf\controller;

interface Controller
{
	/**
	 * Will be executed before the given action.
	 * Can be used to e.g. do some access-checks.
	 *
	 * @return void
	 * @throws \ampf\exceptions\ControllerInterruptedException In case the action should not be executed
	 */
	public function beforeAction();

	/**
	 * Will be executed after the given action.
	 * Can be used to e.g. set a surrounding layout around the response.
	 *
	 * @return void
	 */
	public function afterAction();

	/**
	 * Execute the given action. The main logic of a controller goes here.
	 *
	 * @return void
	 */
	public function execute();
}
