<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;

use Illuminate\Foundation\Application;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data source for Laravel framework, provides application log, timeline, request and response information
 */
class LaravelDataSource extends DataSource
{
	/**
	 * Laravel application from which the data is retrieved
	 */
	protected $app;

	/**
	 * Laravel response from which the data is retrieved
	 */
	protected $response;

	/**
	 * Log data structure
	 */
	protected $log;

	/**
	 * Timeline data structure
	 */
	protected $timeline;

	/**
	 * Timeline data structure for views data
	 */
	protected $views;

	/**
	 * Create a new data source, takes Laravel application instance as an argument
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;

		$this->log      = new Log();
		$this->timeline = new Timeline();
		$this->views    = new Timeline();
	}

	/**
	 * Adds request method, uri, controller, headers, response status, timeline data and log entries to the request
	 */
	public function resolve(Request $request)
	{
		$request->method         = $this->getRequestMethod();
		$request->uri            = $this->getRequestUri();
		$request->controller     = $this->getController();
		$request->headers        = $this->getRequestHeaders();
		$request->responseStatus = $this->getResponseStatus();
		$request->routes         = $this->getRoutes();
		$request->sessionData    = $this->getSessionData();

		$request->log          = array_merge($request->log, $this->log->toArray());
		$request->timelineData = $this->timeline->finalize($request->time);
		$request->viewsData    = $this->views->finalize();

		return $request;
	}

	/**
	 * Set a custom response instance
	 */
	public function setResponse(Response $response)
	{
		$this->response = $response;
	}

	/**
	 * Hook up callbacks for various Laravel events, providing information for timeline and log entries
	 */
	public function listenToEvents()
	{
		$this->app['events']->listen('clockwork.controller.start', function () {
			$this->timeline->startEvent('controller', 'Controller running.');
		});
		$this->app['events']->listen('clockwork.controller.end', function () {
			$this->timeline->endEvent('controller');
		});

		if (class_exists('Illuminate\Log\Events\MessageLogged')) {
			// Laravel 5.4
			$this->app['events']->listen('Illuminate\Log\Events\MessageLogged', function ($event) {
				$this->log->log($event->level, $event->message, $event->context);
			});
		} else {
			// Laravel 4.0 to 5.3
			$this->app['events']->listen('illuminate.log', function ($level, $message, $context) {
				$this->log->log($level, $message, $context);
			});
		}

		$this->app['events']->listen('composing:*', function ($view, $data = null) {
			if (is_string($view) && is_array($data)) { // Laravel 5.4 wildcard event
				$view = $data[0];
			}

			$time = microtime(true);

			$this->views->addEvent(
				'view ' . $view->getName(),
				'Rendering a view',
				$time,
				$time,
				[ 'name' => $view->getName(), 'data' => Serializer::simplify($view->getData()) ]
			);
		});
	}

	/**
	 * Hook up callbacks for some Laravel events, that we need to register as soon as possible
	 */
	public function listenToEarlyEvents()
 	{
		$this->timeline->startEvent('total', 'Total execution time.', 'start');
		$this->timeline->startEvent('initialisation', 'Application initialisation.', 'start');

 		$this->app->booting(function () {
 			$this->timeline->endEvent('initialisation');
 			$this->timeline->startEvent('boot', 'Framework booting.');
 			$this->timeline->startEvent('run', 'Framework running.');
 		});

 		$this->app->booted(function () {
 			$this->timeline->endEvent('boot');
 		});
 	}

	/**
	 * Return a textual representation of current route's controller
	 */
	protected function getController()
	{
		$router = $this->app['router'];

		if (strpos(Application::VERSION, '4.0') === 0) { // Laravel 4.0
			$route = $router->getCurrentRoute();
			$controller = $route ? $route->getAction() : null;
		} else { // Laravel 4.1
			$route = $router->current();
			$controller = $route ? $route->getActionName() : null;
		}

		if ($controller instanceof \Closure) {
			$controller = 'anonymous function';
		} elseif (is_object($controller)) {
			$controller = 'instance of ' . get_class($controller);
		} elseif (is_array($controller) && count($controller) == 2) {
			if (is_object($controller[0])) {
				$controller = get_class($controller[0]) . '->' . $controller[1];
			} else {
				$controller = $controller[0] . '::' . $controller[1];
			}
		} elseif (! is_string($controller)) {
			$controller = null;
		}

		return $controller;
	}

	/**
	 * Return request headers
	 */
	protected function getRequestHeaders()
	{
		return $this->app['request']->headers->all();
	}

	/**
	 * Return request method
	 */
	protected function getRequestMethod()
	{
		return $this->app['request']->getMethod();
	}

	/**
	 * Return request URI
	 */
	protected function getRequestUri()
	{
		return $this->app['request']->getRequestUri();
	}

	/**
	 * Return response status code
	 */
	protected function getResponseStatus()
	{
		return $this->response->getStatusCode();
	}

	/**
	 * Return array of application routes
	 */
	protected function getRoutes()
	{
		$router = $this->app['router'];

		if (strpos(Application::VERSION, '4.0') === 0) { // Laravel 4.0
			$routes = $router->getRoutes()->all();
			$names = array_keys($routes);

			return array_map(function ($route, $name) {
				return [
					'method' => implode(', ', $route->getMethods()),
					'uri'    => $route->getPath(),
					'name'   => $name,
					'action' => $route->getAction() ?: 'anonymous function',
					'before' => implode(', ', $route->getBeforeFilters()),
					'after'  => implode(', ', $route->getAfterFilters())
				];
			}, $routes, $names);
		} else { // Laravel 4.1
			$routes = $router->getRoutes()->getRoutes();

			return array_map(function ($route) {
				return [
					'method' => implode(', ', $route->methods()),
					'uri'    => $route->uri(),
					'name'   => $route->getName(),
					'action' => $route->getActionName() ?: 'anonymous function',
					'middleware' => is_callable([ $route, 'middleware' ]) ? $route->middleware() : [],
					'before' => method_exists($route, 'beforeFilters') ? implode(', ', array_keys($route->beforeFilters())) : '',
					'after'  => method_exists($route, 'afterFilters') ? implode(', ', array_keys($route->afterFilters())) : ''
				];
			}, $routes);
		}
	}

	/**
	 * Return session data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getSessionData()
	{
		return $this->removePasswords(Serializer::simplify($this->app['session']->all()));
	}
}
