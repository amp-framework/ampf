<?php

declare(strict_types=1);

namespace ampf\Tests\Fixtures\RuleAbidingApp\Constant;

/** The route ids of the application, in another order than its configuration's. */
abstract class RouteConstants
{
    public const string ROUTE_ID_NOT_FOUND = 'not-found';
    public const string ROUTE_ID_HOME = 'home';
    public const string ROUTE_ID_HELLO = 'hello';

    public const string CLI_ROUTE_ID_HELP = 'help';
    public const string CLI_ROUTE_ID_GREET = 'greet';
    public const string CLI_ROUTE_ID_BEAN_ACCESS = 'beanAccess/generate';

    /** Not a route's: the guard leaves it alone. */
    public const int PAGE_SIZE = 20;
}
