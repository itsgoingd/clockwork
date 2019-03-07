<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Request;

/**
 * Data source providing data obtainable from plain PHP
 */
class PhpDataSource extends DataSource
{
	/**
	 * Add request time, method, URI, headers, get and post data, session data, cookies, response status, response time
	 * and peak memory usage to the request
	 */
	public function resolve(Request $request)
	{
		$request->time           = $this->getRequestTime();
		$request->method         = $this->getRequestMethod();
		$request->url            = $this->getRequestUrl();
		$request->uri            = $this->getRequestUri();
		$request->headers        = $this->getRequestHeaders();
		$request->getData        = $this->getGetData();
		$request->postData       = $this->getPostData();
		$request->requestData    = $this->getRequestData();
		$request->sessionData    = $this->getSessionData();
		$request->cookies        = $this->getCookies();
		$request->responseStatus = $this->getResponseStatus();
		$request->responseTime   = $this->getResponseTime();
		$request->memoryUsage    = $this->getMemoryUsage();

		return $request;
	}

	/**
	 * Return cookies (replace unserializable items, attempt to remove passwords)
	 */
	protected function getCookies()
	{
		return $this->removePasswords((new Serializer)->normalizeEach($_COOKIE));
	}

	/**
	 * Return GET data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getGetData()
	{
		return $this->removePasswords((new Serializer)->normalizeEach($_GET));
	}

	/**
	 * Return POST data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getPostData()
	{
		return $this->removePasswords((new Serializer)->normalizeEach($_POST));
	}

	// Return request body data (attempt to parse as json, replace unserializable items, attempt to remove passwords)
	protected function getRequestData()
	{
		$requestData = file_get_contents('php://input');
		$requestJsonData = json_decode($requestData, true);

		return $requestJsonData
			? $this->removePasswords((new Serializer)->normalizeEach($requestJsonData))
			: $requestData;
	}

	/**
	 * Return headers
	 */
	protected function getRequestHeaders()
	{
		$headers = [];

		foreach ($_SERVER as $key => $value) {
			if (substr($key, 0, 5) !== 'HTTP_') continue;

			$header = substr($key, 5);
			$header = str_replace('_', ' ', $header);
			$header = ucwords(strtolower($header));
			$header = str_replace(' ', '-', $header);

			if (! isset($headers[$header])) {
				$headers[$header] = [ $value ];
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
		if (isset($_SERVER['REQUEST_METHOD'])) {
			return $_SERVER['REQUEST_METHOD'];
		}
	}

	/**
	 * Return response time in most precise form
	 */
	protected function getRequestTime()
	{
		if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
			return $_SERVER['REQUEST_TIME_FLOAT'];
		}
	}

	/**
	 * Return request URL
	 */
	protected function getRequestUrl()
	{
		$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';
		$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
		$addr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
		$port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : null;
		$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;

		$scheme = $https ? 'https' : 'http';
		$host = $host ?: $addr;
		$port = (! $https && $port != 80 || $https && $port != 443) ? ":{$port}" : '';

		return "{$scheme}://{$host}{$port}{$uri}";
	}

	/**
	 * Return request URI
	 */
	protected function getRequestUri()
	{
		if (isset($_SERVER['REQUEST_URI'])) {
			return $_SERVER['REQUEST_URI'];
		}
	}

	/**
	 * Return response status code
	 */
	protected function getResponseStatus()
	{
		return http_response_code();
	}

	/**
	 * Return response time (current time, assuming most application scripts have already run at this point)
	 */
	protected function getResponseTime()
	{
		return microtime(true);
	}

	/**
	 * Return session data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getSessionData()
	{
		if (! isset($_SESSION)) {
			return [];
		}

		return $this->removePasswords((new Serializer)->normalizeEach($_SESSION));
	}

	// Return peak memory usage in bytes
	protected function getMemoryUsage()
	{
		return memory_get_peak_usage(true);
	}
}
