<?php namespace Clockwork\Web;

class Web
{
	public function asset($path)
	{
		$path = $this->resolveAssetPath($path);

		if (! $path) return;

		switch (pathinfo($path, PATHINFO_EXTENSION)) {
			case 'css':
				$mime = 'text/css';
				break;
			case 'js':
				$mime = 'application/javascript';
				break;
			default:
				$mime = 'text/html';
				break;
		}

		return [
			'path' => $path,
			'mime' => $mime
		];
	}

	protected function resolveAssetPath($path)
	{
		$publicPath = realpath(__DIR__ . '/public');

		$path = realpath("$publicPath/{$path}");

		return strpos($path, $publicPath) === 0 ? $path : false;
	}
}
