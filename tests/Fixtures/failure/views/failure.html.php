<?php

declare(strict_types=1);

/** @var \ampf\View\HttpView $this */
/** @var string $reference */

?>
<h1><?= $this->t('FAILURE_TITLE'); ?></h1>
<p><?= $this->te('FAILURE_LEAD', [$reference]); ?></p>
