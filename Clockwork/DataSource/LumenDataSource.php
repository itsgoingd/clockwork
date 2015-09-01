<?php
namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Illuminate\Contracts\Foundation\Application;
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

		$this->log = new Log();
		$this->timeline = new Timeline();
		$this->views = new Timeline();
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
		$timeline = $this->timeline;

		$timeline->startEvent('total', 'Total execution time.', 'start');

		$this->app['events']->listen('clockwork.controller.start', function() use($timeline)
		{
			$timeline->startEvent('controller', 'Controller running.');
		});
		$this->app['events']->listen('clockwork.controller.end', function() use($timeline)
		{
			$timeline->endEvent('controller');
		});

		$log = $this->log;

		$this->app['events']->listen('illuminate.log', function($level, $message, $context) use($log)
		{
			$log->log($level, $message, $context);
		});

		$views = $this->views;
		$that = $this;

		$this->app['events']->listen('composing:*', function($view) use($views, $that)
		{
			$time = microtime(true);

			$views->addEvent(
				'view ' . $view->getName(),
				'Rendering a view',
				$time,
				$time,
				array(
					'name' => $view->getName(),
					'data' => $that->replaceUnserializable($view->getData())
				)
			);
		});
	}

	/**
	 * Return a textual representation of current route's controller
	 */
	protected function getController()
	{
		$routes = $this->app->getRoutes();

		$method = $this->getMethod();
		$pathInfo = $this->app->getPathInfo();

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
		} else if (!is_string($controller)) {
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
		$routesData = array();

		$routes = $this->app->getRoutes();

		foreach ($routes as $route) {
			$routesData[] = array(
				'method' => $route['method'],
				'uri'    => $route['uri'],
				'name'   => array_search($route['uri'], $this->app->namedRoutes) ?: null,
				'action' => isset($route['action']['uses']) && is_string($route['action']['uses']) ? $route['action']['uses'] : 'anonymous function'
			);
		}

		return $routesData;
	}

	/**
	 * Return session data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getSessionData()
	{
		return $this->removePasswords(
			$this->replaceUnserializable($this->app['session']->all())
		);
	}

    protected function getMethod()
    {
        if (isset($_POST['_method'])) {
            return strtoupper($_POST['_method']);
        } else {
            return $_SERVER['REQUEST_METHOD'];
        }
    }
}
