<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;

use Laravel\Lumen\Application;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data source for Lumen framework, provides application log, timeline, request and response information
 */
class LumenDataSource extends DataSource
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
		$this->timeline->startEvent('total', 'Total execution time.', 'start');

		$this->app['events']->listen('clockwork.controller.start', function () {
			$this->timeline->startEvent('controller', 'Controller running.');
		});
		$this->app['events']->listen('clockwork.controller.end', function () {
			$this->timeline->endEvent('controller');
		});

		$this->app['events']->listen('illuminate.log', function ($level, $message, $context) {
			$this->log->log($level, $message, $context);
		});

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
	 * Return a textual representation of current route's controller
	 */
	protected function getController()
	{
		$routes = method_exists($this->app, 'getRoutes') ? $this->app->getRoutes() : [];

		$method = $this->getMethod();
		$pathInfo = $this->getPathInfo();

		if (isset($routes[$method.$pathInfo]['action']['uses'])) {
			$controller = $routes[$method.$pathInfo]['action']['uses'];
		} elseif (isset($routes[$method.$pathInfo]['action'][0])) {
			$controller = $routes[$method.$pathInfo]['action'][0];
		} else {
			$controller = null;
		}

		if ($controller instanceof \Closure) {
			$controller = 'anonymous function';
		} elseif (is_object($controller)) {
			$controller = 'instance of ' . get_class($controller);
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
		if (isset($this->app->router)) {
			$routes = array_values($this->app->router->getRoutes());
		} elseif (method_exists($this->app, 'getRoutes')) {
			$routes = array_values($this->app->getRoutes());
		} else {
			$routes = [];
		}

		return array_map(function ($route) {
			return [
				'method' => $route['method'],
				'uri'    => $route['uri'],
				'name'   => isset($route['action']['as']) ? $route['action']['as'] : null,
				'action' => isset($route['action']['uses']) && is_string($route['action']['uses']) ? $route['action']['uses'] : 'anonymous function',
				'middleware' => isset($route['action']['middleware']) ? $route['action']['middleware'] : null,
			];
		}, $routes);
	}

	/**
	 * Return session data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getSessionData()
	{
		if (! isset($this->app['session'])) {
			return [];
		}

		return $this->removePasswords(Serializer::simplify($this->app['session']->all()));
	}

	protected function getMethod()
	{
		if ($this->app->bound('Illuminate\Http\Request')) {
			return $this->app['Illuminate\Http\Request']->getMethod();
		} elseif (isset($_POST['_method'])) {
			return strtoupper($_POST['_method']);
		} else {
			return $_SERVER['REQUEST_METHOD'];
		}
	}

	protected function getPathInfo()
	{
		if ($this->app->bound('Illuminate\Http\Request')) {
			return $this->app['Illuminate\Http\Request']->getPathInfo();
		} else {
			$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

			return '/' . trim(str_replace("?{$query}", '', $_SERVER['REQUEST_URI']), '/');
		}
	}
}
