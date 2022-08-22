<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;

use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\HttpFoundation\Response;

// Data source for Laravel framework, provides application log, request and response information
class LaravelDataSource extends DataSource
{
	// Laravel application instance
	protected $app;

	// Laravel response instance
	protected $response;

	// Whether we should collect log messages
	protected $collectLog = true;

	// Whether we should collect routes
	protected $collectRoutes = false;

	// Only collect routes from following list of namespaces (collect all if empty)
	protected $routesOnlyNamespaces = [];

	// Clockwork log instance
	protected $log;

	// Create a new data source, takes Laravel application instance and additional options as an arguments
	public function __construct(Application $app, $collectLog = true, $collectRoutes = false, $routesOnlyNamespaces = true)
	{
		$this->app = $app;

		$this->collectLog           = $collectLog;
		$this->collectRoutes        = $collectRoutes;
		$this->routesOnlyNamespaces = $routesOnlyNamespaces;

		$this->log = new Log;
	}

	// Adds request, response information, middleware, routes, session data, user and log entries to the request
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

		$request->log()->merge($this->log);

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->log = new Log;
	}

	// Set Laravel application instance for the current request
	public function setApplication(Application $app)
	{
		$this->app = $app;
		return $this;
	}

	// Set Laravel response instance for the current request
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

	// Get a textual representation of the current route's controller
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

	// Get the request headers
	protected function getRequestHeaders()
	{
		return $this->app['request']->headers->all();
	}

	// Get the request method
	protected function getRequestMethod()
	{
		return $this->app['request']->getMethod();
	}

	// Get the request URL
	protected function getRequestUrl()
	{
		return $this->app['request']->fullUrl();
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

	// Get an array of middleware for the matched route
	protected function getMiddleware()
	{
		$route = $this->app['router']->current();

		if (! $route) return;

		return method_exists($route, 'gatherMiddleware') ? $route->gatherMiddleware() : $route->middleware();
	}

	// Get an array of application routes
	protected function getRoutes()
	{
		if (! $this->collectRoutes) return [];

		return array_values(array_filter(array_map(function ($route) {
			$action = $route->getActionName() ?: 'anonymous function';
			$namespace = strpos($action, '\\') !== false ? explode('\\', $action)[0] : null;

			if (count($this->routesOnlyNamespaces) && ! in_array($namespace, $this->routesOnlyNamespaces)) return;

			return [
				'method'     => implode(', ', $route->methods()),
				'uri'        => $route->uri(),
				'name'       => $route->getName(),
				'action'     => $action,
				'middleware' => $route->middleware(),
				'before'     => method_exists($route, 'beforeFilters') ? implode(', ', array_keys($route->beforeFilters())) : '',
				'after'      => method_exists($route, 'afterFilters') ? implode(', ', array_keys($route->afterFilters())) : ''
			];
		}, $this->app['router']->getRoutes()->getRoutes())));
	}

	// Get the session data (normalized with removed passwords)
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
			'name'  => !method_exists($user, 'name') && isset($user->name) ? $user->name : null
		]);
	}
}
