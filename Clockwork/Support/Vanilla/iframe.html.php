<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Clockwork</title>

	<link rel="icon" type="image/png" sizes="32x32" href="<?= $asset('img/icons/favicon-32x32.png') ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="<?= $asset('img/icons/favicon-16x16.png') ?>">
	<link rel="manifest" href="<?= $asset('manifest.json') ?>">
	<meta name="theme-color" content="#4DBA87">
	<meta name="apple-mobile-web-app-capable" content="no">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<meta name="apple-mobile-web-app-title" content="Clockwork">
	<link rel="apple-touch-icon" href="<?= $asset('img/icons/apple-touch-icon-152x152.png') ?>">
	<link rel="mask-icon" href="<?= $asset('img/icons/safari-pinned-tab.svg') ?>" color="#4DBA87">
	<meta name="msapplication-TileImage" content="<?= $asset('img/icons/msapplication-icon-144x144.png') ?>">
	<meta name="msapplication-TileColor" content="#000000">

	<style>
		iframe { position: fixed; top: 0; left: 0; height: 100%; width: 100%; border: 0; }
	</style>
</head>
<body>
	<iframe src="<?= $url ?>"></iframe>

	<script>
		let clockworkData = JSON.parse(localStorage.getItem('clockwork') || '{}')

		clockworkData.settings = clockworkData.settings || {}
		clockworkData.settings.global = clockworkData.settings.global || {}
		clockworkData.settings.global.metadataPath = '<?= $metadataPath ?>'

		localStorage.setItem('clockwork', JSON.stringify(clockworkData))
	</script>
</html>
