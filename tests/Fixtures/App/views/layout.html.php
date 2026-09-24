<?php

declare(strict_types=1);

/** @var \ampf\View\HttpView $this */
/** @var string $title */
/** @var string $content */

?>
<!DOCTYPE html>
<html lang="en">
<head><title><?= $this->escape($title); ?></title></head>
<body>
<?= $content; ?>
</body>
</html>
