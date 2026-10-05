<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\RuleBreakingApp\Constant;

/** Route constants of which one is a number, not the text of a route id. */
abstract class NumberedRouteConstants
{
    public const string ROUTE_ID_HOME = 'home';
    public const int ROUTE_ID_COUNT = 3;
}
