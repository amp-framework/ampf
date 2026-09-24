<?php

declare(strict_types=1);

/** @var \ampf\View\HttpView $this */
/** @var list<\ampf\Tests\Fixtures\App\Doctrine\Entity\NoteEntity> $notes */

?>
<ul>
<?php foreach ($notes as $note): ?>
    <li><?= $this->escape($note->getText()); ?> (<?= $this->formatTime($note->getWrittenAt(), 'Y-m-d'); ?>)</li>
<?php endforeach; ?>
</ul>
<form method="post" action="<?= $this->escape($this->getActionLink('notes', null, true)); ?>">
    <input name="text" value="<?= $this->escape($this->getParamString('text')); ?>">
</form>
