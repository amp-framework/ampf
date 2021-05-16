<?php

declare(strict_types=1);

namespace ampf\controller;

interface Controller
{
    /**
     * Will be executed before the given action.
     * Can be used to e.g. do some access-checks.
     *
     * Should throw \ampf\exceptions\ControllerInterruptedException In case the action should not be executed
     */
    public function beforeAction(): void;

    /**
     * Will be executed after the given action.
     * Can be used to e.g. set a surrounding layout around the response.
     */
    public function afterAction(): void;

    /**
     * Execute the given action. The main logic of a controller goes here.
     */
    public function execute(): void;
}
