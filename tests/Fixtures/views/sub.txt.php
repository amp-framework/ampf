<?php

declare(strict_types=1);

/** @var \ampf\View\AbstractView $this */
/** @var string $title */

echo $title, '|other: ', var_export($this->has('other'), true);
