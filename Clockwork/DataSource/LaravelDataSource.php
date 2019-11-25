<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;

use Illuminate\Contracts\Foundation\Application;
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

	// Whether we should collect log messages
	protected $collectLog = true;

	// Whether we should collect views
	protected $collectViews = false;

	// Whether we should collect routes
	protected $collectRoutes = false;

	/**
	 * Create a new data source, takes Laravel application instance as an argument
	 */
	public function __construct(Application $app, $collectLog = true, $collectViews = false, $collectRoutes = false)
	{
		$this->app = $app;
		$this->collectLog = $collectLog;
		$this->collectViews = $collectViews;
		$this->collectRoutes = $collectRoutes;

		$this->timeline = new Timeline();
		$this->views    = new Timeline();
	}

	/**
	 * Adds request method, uri, controller, headers, response status, timeline data and log entries to the request
	 */
	public function resolve(Request $request)
	{
		$request->method         = $this->getRequestMethod();
		$request->url            = $this->getRequestUrl();
		$request->uri            = $this->getRequestUri();
		$request->controller     = $this->getController();
		$request->headers        = $this->getRequestHeaders();
		$request->responseStatus = $this->getResponseStatus();
		$request->middleware     = $this->getMiddleware();
		$request->routes         = $this->getRoutes();
		$request->sessionData    = $this->getSessionData();

		$this->resolveAuthenticatedUser($request);

		$request->timelineData = $this->timeline->finalize($request->time);
		$request->viewsData    = $this->views->finalize();

		return $request;
	}

	// Set a log instance
	public function setLog(Log $log)
	{
		$this->log = $log;
		return $this;
	}

	/**
	 * Set a custom response instance
	 */
	public function setResponse(Response $response)
	{
		$this->response = $response;
		return $this;
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

		if ($this->collectLog) {
			if (class_exists(\Illuminate\Log\Events\MessageLogged::class)) {
				// Laravel 5.4
				$this->app['events']->listen(\Illuminate\Log\Events\MessageLogged::class, function ($event) {
					$this->log->log($event->level, $event->message, $event->context);
				});
			} else {
				// Laravel 5.0 to 5.3
				$this->app['events']->listen('illuminate.log', function ($level, $message, $context) {
					$this->log->log($level, $message, $context);
				});
			}
		}

		if ($this->collectViews) {
			$this->app['events']->listen('composing:*', function ($view, $data = null) {
				if (is_string($view) && is_array($data)) { // Laravel 5.4 wildcard event
					$view = $data[0];
				}

				$time = microtime(true);
				$data = $view->getData();
				unset($data['__env']);

				$this->views->addEvent(
					'view ' . $view->getName(),
					'Rendering a view',
					$time,
					$time,
					[ 'name' => $view->getName(), 'data' => (new Serializer)->normalize($data) ]
				);
			});
		}
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

		$route = $router->current();
		$controller = $route ? $route->getActionName() : null;

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
	 * Return request URL
	 */
	protected function getRequestUrl()
	{
		return $this->app['request']->url();
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

	// Return array of middleware for the matched route
	protected function getMiddleware()
	{
		$route = $this->app['router']->current();

		if (! $route) return;

		return method_exists($route, 'gatherMiddleware') ? $route->gatherMiddleware() : $route->middleware();
	}

	/**
	 * Return array of application routes
	 */
	protected function getRoutes()
	{
		if (! $this->collectRoutes) return [];

		return array_map(function ($route) {
			return [
				'method' => implode(', ', $route->methods()),
				'uri'    => $route->uri(),
				'name'   => $route->getName(),
				'action' => $route->getActionName() ?: 'anonymous function',
				'middleware' => $route->middleware(),
				'before' => method_exists($route, 'beforeFilters') ? implode(', ', array_keys($route->beforeFilters())) : '',
				'after'  => method_exists($route, 'afterFilters') ? implode(', ', array_keys($route->afterFilters())) : ''
			];
		}, $this->app['router']->getRoutes()->getRoutes());
	}

	/**
	 * Return session data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getSessionData()
	{
		if (! isset($this->app['session'])) {
			return [];
		}

		return $this->removePasswords((new Serializer)->normalizeEach($this->app['session']->all()));
	}

	// Add authenticated user data to the request
	protected function resolveAuthenticatedUser(Request $request)
	{
		if (! $this->app->bound('auth')) return;
		if (! ($user = $this->app['auth']->user())) return;
		if (! isset($user->email) || ! isset($user->id)) return;

		$request->setAuthenticatedUser($user->email, $user->id, [
			'email' => isset($user->email) ? $user->email : null,
			'name'  => isset($user->name) ? $user->name : null
		]);
	}
}
