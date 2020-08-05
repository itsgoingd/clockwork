<?php namespace Clockwork\Support\Symfony;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ClockworkLoader extends Loader
{
	protected $support;

	public function __construct(ClockworkSupport $support)
	{
		$this->support = $support;
	}

	public function load($resource, $type = null)
	{
		$routes = new RouteCollection();

		$routes->add('clockwork', new Route('/__clockwork/{id}/{direction}/{count}', [
			'_controller' => [ ClockworkController::class, 'getData' ],
			'direction' => null,
			'count' => null
		], [ 'id' => '(?!(app|auth))([a-z0-9-]+|latest)', 'direction' => '(next|previous)', 'count' => '\d+' ]));

		$routes->add('clockwork.auth', new Route('/__clockwork/auth', [
			'_controller' => [ ClockworkController::class, 'authenticate' ]
		]));

		if (! $this->support->isWebEnabled()) return $routes;

		foreach ($this->support->webPaths() as $path) {
			$routes->add("clockwork.webRedirect.{$path}", new Route("{$path}", [
				'_controller' => [ ClockworkController::class, 'webRedirect' ]
			]));

			$routes->add("clockwork.webIndex.{$path}", new Route("{$path}/app", [
				'_controller' => [ ClockworkController::class, 'webIndex' ]
			]));

			$routes->add("clockwork.webAsset.{$path}", new Route("{$path}/{path}", [
				'_controller' => [ ClockworkController::class, 'webAsset' ]
			], [ 'path' => '.+' ]));
		}

		return $routes;
	}

	public function supports($resource, $type = null)
	{
		return $type == 'clockwork';
	}
}
