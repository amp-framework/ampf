<?php

declare(strict_types=1);

/*
 * The configuration of the fixture application: it opts into the session that does not start for a read without a
 * cookie.
 */
return [
    'session' => ['lazy' => true],
];
