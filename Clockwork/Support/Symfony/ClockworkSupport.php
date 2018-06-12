<?php namespace Clockwork\Support\Symfony;

use Clockwork\Web\Web;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClockworkSupport
{
	protected $container;
	protected $config;

	public function __construct(ContainerInterface $container, $config)
	{
		$this->container = $container;
		$this->config = $config;
	}

	public function getConfig($key, $default = null)
	{
		return isset($this->config[$key]) ? $this->config[$key] : $default;
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		$storage = $this->container->get('clockwork')->getStorage();

		if ($direction == 'previous') {
			$data = $storage->previous($id, $count);
		} elseif ($direction == 'next') {
			$data = $storage->next($id, $count);
		} elseif ($id == 'latest') {
			$data = $storage->latest();
		} else {
			$data = $storage->find($id);
		}

		$data = is_array($data)
			? array_map(function ($request) { return $request->toArray(); }, $data)
			: $data->toArray();

		return new JsonResponse($data);
	}

	public function getWebAsset($path)
	{
		$web = new Web;

		if ($asset = $web->asset($path)) {
			return new BinaryFileResponse($asset['path'], 200, [ 'Content-Type' => $asset['mime'] ]);
		} else {
			throw new NotFoundHttpException;
		}
	}

	public function isEnabled()
	{
		return $this->getConfig('enable', false);
	}

	public function isWebUsingDarkTheme()
	{
		return $this->getConfig('web_dark_theme', false);
	}
}
