<?php namespace Clockwork\Support\Symfony;

use Clockwork\Authentication\{NullAuthenticator, SimpleAuthenticator};
use Clockwork\Storage\Search;
use Clockwork\Web\Web;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, JsonResponse, Request};
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

	public function getData(Request $request, $id = null, $direction = null, $count = null)
	{
		$authenticator = $this->container->get('clockwork')->authenticator();
		$storage = $this->container->get('clockwork')->storage();

		$authenticated = $authenticator->check($request->headers->get('X-Clockwork-Auth'));

		if ($authenticated !== true) {
			return new JsonResponse([ 'message' => $authenticated, 'requires' => $authenticator->requires() ], 403);
		}

		if ($direction == 'previous') {
			$data = $storage->previous($id, $count, Search::fromRequest($request->query->all()));
		} elseif ($direction == 'next') {
			$data = $storage->next($id, $count, Search::fromRequest($request->query->all()));
		} elseif ($id == 'latest') {
			$data = $storage->latest(Search::fromRequest($request->query->all()));
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

	public function makeAuthenticator()
	{
		$authenticator = $this->getConfig('authentication');

		if (is_string($authenticator)) {
			return $this->container->get($authenticator);
		} elseif ($authenticator) {
			return new SimpleAuthenticator($this->getConfig('authentication_password'));
		} else {
			return new NullAuthenticator;
		}
	}

	public function isEnabled()
	{
		return $this->getConfig('enable', false);
	}

	public function isWebEnabled()
	{
		return $this->getConfig('web', true);
	}

	public function webPaths()
	{
		$path = $this->getConfig('web', true);

		if (is_string($path)) return [ trim($path, '/') ];

		return [ 'clockwork', '__clockwork' ];
	}
}
