<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Request;

// Data source providing data obtainable in vanilla PHP
class PhpDataSource extends DataSource
{
	// Adds request, response information, session data and peak memory usage to the request
	public function resolve(Request $request)
	{
		$request->time           = PHP_SAPI !== 'cli' ? $this->getRequestTime() : $request->time;
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

	// Get the request cookies (normalized with passwords removed)
	protected function getCookies()
	{
		return $this->removePasswords((new Serializer)->normalizeEach($_COOKIE));
	}

	// Get the request GET data (normalized with passwords removed)
	protected function getGetData()
	{
		return $this->removePasswords((new Serializer)->normalizeEach($_GET));
	}

	// Get the request POST data (normalized with passwords removed)
	protected function getPostData()
	{
		return $this->removePasswords((new Serializer)->normalizeEach($_POST));
	}

	// Get the request body data (attempt to parse as json, normalized with passwords removed)
	protected function getRequestData()
	{
		// The data will already be parsed into POST data by PHP in case of application/x-www-form-urlencoded requests
		if (count($_POST)) return;

		$requestData = file_get_contents('php://input');
		$requestJsonData = json_decode($requestData, true);

		return is_array($requestJsonData)
			? $this->removePasswords((new Serializer)->normalizeEach($requestJsonData))
			: $requestData;
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

			$headers[$header] = array_merge($headers[$header] ?? [], [ $value ]);
		}

		ksort($headers);

		return $headers;
	}

	// Get the request method
	protected function getRequestMethod()
	{
		return $_SERVER['REQUEST_METHOD'] ?? null;
	}

	// Get the response time
	protected function getRequestTime()
	{
		return $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
	}

	// Get the request URL
	protected function getRequestUrl()
	{
		$https = ($_SERVER['HTTPS'] ?? null) == 'on';
		$host = $_SERVER['HTTP_HOST'] ?? null;
		$addr = $_SERVER['SERVER_ADDR'] ?? null;
		$port = $_SERVER['SERVER_PORT'] ?? null;
		$uri = $_SERVER['REQUEST_URI'] ?? null;

		$scheme = $https ? 'https' : 'http';
		$host = $host ?: $addr;
		$port = (! $https && $port != 80 || $https && $port != 443) ? ":{$port}" : '';

		// remove port number from the host
		$host = $host ? preg_replace('/:\d+$/', '', trim($host)) : null;

		return "{$scheme}://{$host}{$port}{$uri}";
	}

	// Get the request URI
	protected function getRequestUri()
	{
		return $_SERVER['REQUEST_URI'] ?? null;
	}

	// Get the response status code
	protected function getResponseStatus()
	{
		return http_response_code();
	}

	// Get the response time (current time, assuming most of the application code has already run at this point)
	protected function getResponseTime()
	{
		return microtime(true);
	}

	// Get the session data (normalized with passwords removed)
	protected function getSessionData()
	{
		if (! isset($_SESSION)) return [];

		return $this->removePasswords((new Serializer)->normalizeEach($_SESSION));
	}

	// Get the peak memory usage in bytes
	protected function getMemoryUsage()
	{
		return memory_get_peak_usage(true);
	}
}
