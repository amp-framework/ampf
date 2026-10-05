<?php

declare(strict_types=1);

/*
 * The fixture application's configuration for its tests, in place of config/local.php: the last file an
 * ApplicationTestCase over the application boots (ApplicationTestCase::configurationFiles()).
 */
return [
    'configuration.service' => [
        '.app' => ['greeting' => 'Hello from the tests', 'audience' => 'everyone'],
        '.other' => ['colour' => 'green'],
    ],
];
