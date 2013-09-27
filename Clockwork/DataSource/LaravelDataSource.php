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
	 * Create a new data source, takes Laravel application instance as an argument
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
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

		$request->timelineData = $this->getTimeline()->finalize($request->time);
		$request->log          = array_merge($request->log, $this->getLog()->toArray());

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
	 * Return the log data structure (creates default Log instance if none set)
	 */
	public function getLog()
	{
		if (!$this->log)
			$this->log = new Log();

		return $this->log;
	}

	/**
	 * Set a custom log data structure
	 */
	public function setLog(Log $log)
	{
		$this->log = $log;
	}

	/**
	 * Return the timeline data structure (creates default Timeline instance if none set)
	 */
	public function getTimeline()
	{
		if (!$this->timeline)
			$this->timeline = new Timeline();

		return $this->timeline;
	}

	/**
	 * Set a custom timeline data-structure
	 */
	public function setTimeline(Timeline $timeline)
	{
		$this->timeline = $timeline;
	}

	/**
	 * Hook up callbacks for various Laravel events, providing information for timeline and log entries
	 */
	public function listenToEvents()
	{
		$timeline = $this->getTimeline();

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

		$log = $this->getLog();

		$this->app['events']->listen('illuminate.log', function($level, $message) use($log)
		{
			switch ($level) {
				case 'debug': $level = Log::DEBUG; break;
				case 'info': $level = Log::INFO; break;
				case 'notice': $level = Log::NOTICE; break;
				case 'warning': $level = Log::WARNING; break;
				case 'error': $level = Log::ERROR; break;
				case 'critical': $level = Log::ERROR; break;
				case 'alert': $level = Log::WARNING; break;
				case 'emergency': $level = Log::ERROR; break;
				default: $level = Log::INFO;
			}

			$log->log($message, $level);
		});
	}

	/**
	 * Return a textual representation of current route's controller
	 */
	protected function getController()
	{
		$controller = $this->app['router']->getCurrentRoute()->getAction();

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
		$routes = $this->app['router']->getRoutes()->all();

		$routesData = array();
		foreach ($routes as $name => $route) {
			$routesData[] = array(
				'method' => implode(', ', $route->getMethods()),
				'uri' => $route->getPath(),
				'name' => $name,
				'action' => $route->getAction() ?: 'anonymous function',
				'before' => implode(', ', $route->getBeforeFilters()),
				'after' => implode(', ', $route->getAfterFilters()),
			);
		}

		return $routesData;
	}
}
