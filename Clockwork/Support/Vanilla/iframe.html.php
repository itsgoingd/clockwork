<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Clockwork</title>
		<link rel="icon" href="<?= $asset('img/icon-128x128.png') ?>">
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
	</body>
</html>
