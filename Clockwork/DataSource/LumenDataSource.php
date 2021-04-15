<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;

use Laravel\Lumen\Application;
use Symfony\Component\HttpFoundation\Response;

// Data source for Lumen framework, provides application log, request and response information
class LumenDataSource extends DataSource
{
	// Lumen application instance
	protected $app;

	// Lumen response instance
	protected $response;

	// Whether we should collect log messages
	protected $collectLog = true;

	// Whether we should collect routes
	protected $collectRoutes = false;

	// Clockwork log instance
	protected $log;

	// Create a new data source, takes Lumen application instance and additional options as arguments
	public function __construct(Application $app, $collectLog = true, $collectRoutes = false)
	{
		$this->app = $app;

		$this->collectLog    = $collectLog;
		$this->collectRoutes = $collectRoutes;

		$this->log = new Log;
	}

	// Adds request, response information, middleware, routes, session data, user and log entries to the request
	public function resolve(Request $request)
	{
		$request->method         = $this->getRequestMethod();
		$request->uri            = $this->getRequestUri();
		$request->controller     = $this->getController();
		$request->headers        = $this->getRequestHeaders();
		$request->responseStatus = $this->getResponseStatus();
		$request->routes         = $this->getRoutes();
		$request->sessionData    = $this->getSessionData();

		$this->resolveAuthenticatedUser($request);

		$request->log()->merge($this->log);

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->log = new Log;
	}

	// Set Lumen response instance for the current request
	public function setResponse(Response $response)
	{
		$this->response = $response;
		return $this;
	}

	// Listen for the log events
	public function listenToEvents()
	{
		if (! $this->collectLog) return;

		if (class_exists(\Illuminate\Log\Events\MessageLogged::class)) {
			// Lumen 5.4
			$this->app['events']->listen(\Illuminate\Log\Events\MessageLogged::class, function ($event) {
				$this->log->log($event->level, $event->message, $event->context);
			});
		} else {
			// Lumen 5.0 to 5.3
			$this->app['events']->listen('illuminate.log', function ($level, $message, $context) {
				$this->log->log($level, $message, $context);
			});
		}
	}

	// Get a textual representation of current route's controller
	protected function getController()
	{
		$routes = method_exists($this->app, 'getRoutes') ? $this->app->getRoutes() : [];

		$method = $this->getRequestMethod();
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

	// Get the request headers
	protected function getRequestHeaders()
	{
		return $this->app['request']->headers->all();
	}

	// Get the request method
	protected function getRequestMethod()
	{
		if ($this->app->bound('request')) {
			return $this->app['request']->getMethod();
		} elseif (isset($_POST['_method'])) {
			return strtoupper($_POST['_method']);
		} else {
			return $_SERVER['REQUEST_METHOD'];
		}
	}

	// Get the request URI
	protected function getRequestUri()
	{
		return $this->app['request']->getRequestUri();
	}

	// Get the response status code
	protected function getResponseStatus()
	{
		return $this->response ? $this->response->getStatusCode() : null;
	}

	// Get an array of application routes
	protected function getRoutes()
	{
		if (! $this->collectRoutes) return [];

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

	// Get the session data (normalized with passwords removed)
	protected function getSessionData()
	{
		if (! isset($this->app['session'])) return [];

		return $this->removePasswords((new Serializer)->normalizeEach($this->app['session']->all()));
	}

	// Add authenticated user data to the request
	protected function resolveAuthenticatedUser(Request $request)
	{
		if (! isset($this->app['auth'])) return;
		if (! ($user = $this->app['auth']->user())) return;
		if (! isset($user->email) || ! isset($user->id)) return;

		$request->setAuthenticatedUser($user->email, $user->id, [
			'email' => $user->email,
			'name'  => isset($user->name) ? $user->name : null
		]);
	}

	// Get the request path info
	protected function getPathInfo()
	{
		if ($this->app->bound('request')) {
			return $this->app['request']->getPathInfo();
		} else {
			$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
			return '/' . trim(str_replace("?{$query}", '', $_SERVER['REQUEST_URI']), '/');
		}
	}
}
