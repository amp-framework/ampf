<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\RuleBreakingApp\Constant;

/** Route ids that miss most of the routes, name one that is gone, and give two transports one value. */
abstract class RouteConstants
{
    public const string ROUTE_ID_HOME = 'home';
    public const string ROUTE_ID_GONE = 'gone';

    public const string CLI_ROUTE_ID_HELP = 'help';
    public const string CLI_ROUTE_ID_HOME = 'home';
}
