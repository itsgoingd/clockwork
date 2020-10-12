<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;

use Laravel\Lumen\Application;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data source for Lumen framework, provides application log, request and response information
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

	// Whether we should collect log messages
	protected $collectLog = true;

	// Whether we should collect routes
	protected $collectRoutes = false;

	/**
	 * Create a new data source, takes Laravel application instance as an argument
	 */
	public function __construct(Application $app, $collectLog = true, $collectRoutes = false)
	{
		$this->app = $app;
		$this->collectLog = $collectLog;
		$this->collectRoutes = $collectRoutes;

		$this->log = new Log;
	}

	/**
	 * Adds request method, uri, controller, headers, response status and log entries to the request
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

		$this->resolveAuthenticatedUser($request);

		$request->log()->merge($this->log);

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->log = new Log;
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
	 * Hook up callbacks for various Laravel events, providing information for log entries
	 */
	public function listenToEvents()
	{
		if ($this->collectLog) {
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

	protected function getMethod()
	{
		if ($this->app->bound(\Illuminate\Http\Request::class)) {
			return $this->app[\Illuminate\Http\Request::class]->getMethod();
		} elseif (isset($_POST['_method'])) {
			return strtoupper($_POST['_method']);
		} else {
			return $_SERVER['REQUEST_METHOD'];
		}
	}

	protected function getPathInfo()
	{
		if ($this->app->bound(\Illuminate\Http\Request::class)) {
			return $this->app[\Illuminate\Http\Request::class]->getPathInfo();
		} else {
			$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

			return '/' . trim(str_replace("?{$query}", '', $_SERVER['REQUEST_URI']), '/');
		}
	}

	// Add authenticated user data to the request
	protected function resolveAuthenticatedUser(Request $request)
	{
		if (! isset($this->app['auth']) || ! ($user = $this->app['auth']->user())) return;

		$request->setAuthenticatedUser($user->email, $user->id, [
			'email' => $user->email,
			'name'  => $user->name
		]);
	}
}
