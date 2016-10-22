<?php if (count($allEntries) == 0): ?>
	No entries found.<br />
<?php else: ?>
	Found entries:<br />
	<?php foreach ($allEntries as $entry): ?>
		<?= $this->escape($entry->getId()); ?>: <?= $this->escape($entry->getEntry()); ?><br />
	<?php endforeach; ?>
<?php endif; ?>
<a href="<?= $this->getActionLink('mapper/test/index', array('add' => '1'), true); ?>">
	Add a new entry
</a><br />
