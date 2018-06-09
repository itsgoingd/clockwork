<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();
$routes->add('clockwork', new Route('/__clockwork/{id}/{direction}/{count}', [
	'_controller' => [ Clockwork\Support\Symfony\ClockworkController::class, 'getData' ],
	'direction' => null,
	'count' => null
], [ 'id' => '([a-z0-9-]+|latest)', 'direction' => '(next|previous)', 'count' => '\d+' ]));

return $routes;
