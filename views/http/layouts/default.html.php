<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8" />
		<title></title>
	</head>
	<body>
		<?= $this->subRender('http/common/header.html.php'); ?>
		This is the default layout.<br />
		Unique action id: <?= $uniqueActionID; ?><br />
		<?= $content; ?>
		<?= $this->subRoute('SubrouteExampleController'); ?>
	</body>
</html>
