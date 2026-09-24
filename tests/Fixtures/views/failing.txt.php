<?php

declare(strict_types=1);

echo 'printed before the failure';

throw new RuntimeException('The template failed.');
