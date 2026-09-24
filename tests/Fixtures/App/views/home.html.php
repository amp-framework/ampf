<?php

declare(strict_types=1);

/** @var \ampf\View\HttpView $this */
/** @var string $name */

?>
<h1><?= $this->te('WELCOME', [$name]); ?></h1>
<a href="<?= $this->escape($this->getActionLink('hello', ['name' => 'ada'])); ?>">Ada</a>
<a href="<?= $this->escape($this->getActionLink('notes')); ?>"><?= $this->t('NOTES'); ?></a>
