<?php namespace Clockwork\Web;

class Web
{
	public function asset($path)
	{
		$path = $this->resolveAssetPath($path);

		if (! $path) return;

		return [
			'path' => $path,
			'mime' => strpos($path, '.css') ? 'text/css' : 'text/html'
		];
	}

	protected function resolveAssetPath($path)
	{
		$publicPath = __DIR__ . '/public';

		$path = realpath("$publicPath/{$path}");

		return strpos($path, $publicPath) === 0 ? $path : false;
	}
}
