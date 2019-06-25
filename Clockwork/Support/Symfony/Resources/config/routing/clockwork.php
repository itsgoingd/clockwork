<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add('clockwork.webRedirect', new Route('/__clockwork', [
	'_controller' => [ Clockwork\Support\Symfony\ClockworkController::class, 'webRedirect' ]
]));

$routes->add('clockwork.webIndex', new Route('/__clockwork/app', [
	'_controller' => [ Clockwork\Support\Symfony\ClockworkController::class, 'webIndex' ]
]));

$routes->add('clockwork.webAsset', new Route('/__clockwork/{path}', [
	'_controller' => [ Clockwork\Support\Symfony\ClockworkController::class, 'webAsset' ]
], [ 'path' => '.+' ]));

$routes->add('clockwork.auth', new Route('/__clockwork/auth', [
	'_controller' => [ Clockwork\Support\Symfony\ClockworkController::class, 'authenticate' ]
]));

$routes->add('clockwork', new Route('/__clockwork/{id}/{direction}/{count}', [
	'_controller' => [ Clockwork\Support\Symfony\ClockworkController::class, 'getData' ],
	'direction' => null,
	'count' => null
], [ 'id' => '([a-z0-9-]+|latest)', 'direction' => '(next|previous)', 'count' => '\d+' ]));

return $routes;
