<?php namespace Clockwork\Web;

/**
 * Class for rendering Clockwork app, assets and iframe html
 */
class Web
{
	/**
	 * Current request id, to be shown in embedded Clockwork app
	 */
	private $currentRequestId;

	/**
	 * Return the html code for embedded Clockwork app
	 */
	public function getIframe()
	{
		ob_start();

		$currentRequestId = $this->currentRequestId;

		include __DIR__ . '/public/iframe.html';

		return ob_get_clean();
	}

	/**
	 * Render main Clockwork app html
	 */
	public function render()
	{
		include __DIR__ . '/public/app.html';
	}

	/**
	 * Render asset from ./public/ directory, specified by path
	 */
	public function renderAsset($path)
	{
		$path = realpath(__DIR__ . '/public/' . $path);

		if (strpos($path, __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR) === false) {
			return;
		}

		if (file_exists($path)) {
			readfile($path);
		}
	}

	/**
	 * Set current request id, to be shown in embedded Clockwork app
	 */
	public function setCurrentRequestId($id)
	{
		$this->currentRequestId = $id;
	}
}
