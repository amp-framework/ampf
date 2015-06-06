Hello from the index view.<br />
Test asset url: <?= $this->getAssetLink('./test.js'); ?><br />
Test action link: <?= $this->getActionLink(
	'index/redirect',
	array('pathInfo' => 'blub')
); ?><br />
