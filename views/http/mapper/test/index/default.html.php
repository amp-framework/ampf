<? if (count($allEntries) == 0): ?>
	No entries found.<br />
<? else: ?>
	Found entries:<br />
	<? foreach ($allEntries as $entry): ?>
		<?= $this->escape($entry->ID); ?>: <?= $this->escape($entry->entry); ?><br />
	<? endforeach; ?>
<? endif; ?>
