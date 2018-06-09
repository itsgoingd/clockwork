<?php namespace Clockwork\Support\Symfony;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

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
			? array_map(function ($request) { return $request->toArray(); }, $request)
			: $data->toArray();

		return new JsonResponse($data);
	}
}
