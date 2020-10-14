<?php namespace Clockwork\Web;

// Helper class for serving app assets
class Web
{
	// Return the absolute path and a mime type of an asset, protects from accessing files outside Clockwork public dir
	public function asset($path)
	{
		$path = $this->resolveAssetPath($path);

		if (! $path) return;

		switch (pathinfo($path, PATHINFO_EXTENSION)) {
			case 'css': $mime = 'text/css'; break;
			case 'js': $mime = 'application/javascript'; break;
			default: $mime = 'text/html'; break;
		}

		return [
			'path' => $path,
			'mime' => $mime
		];
	}

	// Resolves absolute path of the asset, protects from accessing files outside Clockwork public dir
	protected function resolveAssetPath($path)
	{
		$publicPath = realpath(__DIR__ . '/public');

		$path = realpath("$publicPath/{$path}");

		return strpos($path, $publicPath) === 0 ? $path : false;
	}
}
