<?php

declare(strict_types=1);

echo 'printed into the view';
ob_start();
echo 'printed into a buffer of its own';

throw new RuntimeException('The template failed inside its own buffer.');
