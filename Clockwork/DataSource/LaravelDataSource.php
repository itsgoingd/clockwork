<?php
namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Illuminate\Foundation\Application;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data source for Laravel 4 framework, provides application log, timeline, request and response information
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

		$timeline->startEvent('initialisation', 'Application initialisation.', 'start');

		$this->app->booting(function() use($timeline)
		{
			$timeline->endEvent('initialisation');
			$timeline->startEvent('boot', 'Framework booting.');
			$timeline->startEvent('run', 'Framework running.');
		});

		$this->app->booted(function() use($timeline)
		{
			$timeline->endEvent('boot');
		});

		$this->app->shutdown(function() use($timeline)
		{
			$timeline->endEvent('run');
		});

		$this->app->before(function() use($timeline)
		{
			$timeline->startEvent('dispatch', 'Router dispatch.');
		});

		$this->app->after(function() use($timeline)
		{
			$timeline->endEvent('dispatch');
		});

		$this->app['events']->listen('clockwork.controller.start', function() use($timeline)
		{
			$timeline->startEvent('controller', 'Controller running.');
		});
		$this->app['events']->listen('clockwork.controller.end', function() use($timeline)
		{
			$timeline->endEvent('controller');
		});

		$log = $this->log;

		$this->app['events']->listen('illuminate.log', function($level, $message) use($log)
		{
			$log->log($level, $message);
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
		$router = $this->app['router'];

		if (strpos(Application::VERSION, '4.0') === 0) { // Laravel 4.0
			$route = $router->getCurrentRoute();
			$controller = $route ? $route->getAction() : null;
		} else { // Laravel 4.1
			$route = $router->current();
			$controller = $route ? $route->getActionName() : null;
		}

		if ($controller instanceof Closure) {
			$controller = 'anonymous function';
		} elseif (is_object($controller)) {
			$controller = 'instance of ' . get_class($controller);
		} else if (is_array($controller) && count($controller) == 2) {
			if (is_object($controller[0]))
				$controller = get_class($controller[0]) . '->' . $controller[1];
			else
				$controller = $controller[0] . '::' . $controller[1];
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
		$router = $this->app['router'];
		$routesData = array();

		if (strpos(Application::VERSION, '4.0') === 0) { // Laravel 4.0
			$routes = $router->getRoutes()->all();

			foreach ($routes as $name => $route) {
				$routesData[] = array(
					'method' => implode(', ', $route->getMethods()),
					'uri'    => $route->getPath(),
					'name'   => $name,
					'action' => $route->getAction() ?: 'anonymous function',
					'before' => implode(', ', $route->getBeforeFilters()),
					'after'  => implode(', ', $route->getAfterFilters()),
				);
			}
		} else { // Laravel 4.1
			$routes = $router->getRoutes();

			foreach ($routes as $route) {
				$routesData[] = array(
					'method' => implode(', ', $route->methods()),
					'uri'    => $route->uri(),
					'name'   => $route->getName(),
					'action' => $route->getActionName() ?: 'anonymous function',
					'before' => implode(', ', array_keys($route->beforeFilters())),
					'after'  => implode(', ', array_keys($route->afterFilters())),
				);
			}
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
}
