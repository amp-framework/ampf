<?php

declare(strict_types=1);

/** @var \ampf\View\CliView $this */

echo implode(',', array_keys(get_defined_vars())), '|', $this::class;
