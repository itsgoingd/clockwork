<?php
namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Request;

use Slim\Slim;

/**
 * Data source for Slim 2 framework, provides controller, request and response information
 */
class SlimDataSource extends DataSource
{
	/**
	 * Slim instance from which data is retrieved
	 */
	protected $slim;

	/**
	 * Create a new data source, takes Slim instance as an argument
	 */
	public function __construct(Slim $slim)
	{
		$this->slim = $slim;
	}

	/**
	 * Add request method, URI, controller, headers and response status data to the request
	 */
	public function resolve(Request $request)
	{
		$request->method         = $this->getRequestMethod();
		$request->uri            = $this->getRequestUri();
		$request->controller     = $this->getController();
		$request->headers        = $this->getRequestHeaders();
		$request->responseStatus = $this->getResponseStatus();

		return $request;
	}

	/**
	 * Return textual representation of current route's controller
	 */
	protected function getController()
	{
		$matched_routes = $this->slim->router()->getMatchedRoutes(
			$this->slim->request()->getMethod(), $this->slim->request()->getResourceUri()
		);

		if (!count($matched_routes)) {
			return null;
		}

		$controller = end($matched_routes)->getCallable();

		if ($controller instanceof \Closure) {
			$controller = 'anonymous function';
		} else if (is_object($controller)) {
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
	 * Return headers
	 */
	protected function getRequestHeaders()
	{
		$headers = array();

		foreach ($_SERVER as $key => $value) {
			if (substr($key, 0, 5) !== 'HTTP_')
				continue;

			$header = substr($key, 5);
			$header = str_replace('_', ' ', $header);
			$header = ucwords(strtolower($header));
			$header = str_replace(' ', '-', $header);

			$value = $this->slim->request()->headers($header, $value);

			if (!isset($headers[$header])) {
				$headers[$header] = array($value);
			} else {
				$headers[$header][] = $value;
			}
		}

		ksort($headers);

		return $headers;
	}

	/**
	 * Return request method
	 */
	protected function getRequestMethod()
	{
		return $this->slim->request()->getMethod();
	}

	/**
	 * Return request URI
	 */
	protected function getRequestUri()
	{
		return $this->slim->request()->getPathInfo();
	}

	/**
	 * Return response status code
	 */
	protected function getResponseStatus()
	{
		return $this->slim->response()->status();
	}
}
