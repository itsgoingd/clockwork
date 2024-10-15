<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Request;

use Slim\Slim;

// Data source for Slim 2 framework, provides controller, request and response information
class SlimDataSource extends DataSource
{
	// Slim instance
	protected $slim;

	// Create a new data source, takes Slim instance as an argument
	public function __construct(Slim $slim)
	{
		$this->slim = $slim;
	}

	// Adds request and response information to the request
	public function resolve(Request $request)
	{
		$request->method         = $this->getRequestMethod();
		$request->uri            = $this->getRequestUri();
		$request->controller     = $this->getController();
		$request->headers        = $this->getRequestHeaders();
		$request->responseStatus = $this->getResponseStatus();

		return $request;
	}

	// Get a textual representation of current route's controller
	protected function getController()
	{
		$matchedRoutes = $this->slim->router()->getMatchedRoutes(
			$this->slim->request()->getMethod(), $this->slim->request()->getResourceUri()
		);

		if (! count($matchedRoutes)) return;

		$controller = end($matchedRoutes)->getCallable();

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
		$headers = [];

		foreach ($_SERVER as $key => $value) {
			if (substr($key, 0, 5) !== 'HTTP_') continue;

			$header = substr($key, 5);
			$header = str_replace('_', ' ', $header);
			$header = ucwords(strtolower($header));
			$header = str_replace(' ', '-', $header);

			$value = $this->slim->request()->headers($header, $value);

			$headers[$header] = array_merge($headers[$header] ?? [], [ $value ]);
		}

		ksort($headers);

		return $headers;
	}

	// Get the request method
	protected function getRequestMethod()
	{
		return $this->slim->request()->getMethod();
	}

	// Get the request URI
	protected function getRequestUri()
	{
		return $this->slim->request()->getPathInfo();
	}

	// Get the response status code
	protected function getResponseStatus()
	{
		return $this->slim->response()->status();
	}
}
