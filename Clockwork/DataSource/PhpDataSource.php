<?php
namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Request;

/**
 * Data source providing data obtainable from plain PHP
 */
class PhpDataSource extends DataSource
{
	/**
	 * Add request time, method, URI, headers, get and post data, session data, cookies, response status and time to the request
	 */
	public function resolve(Request $request)
	{
		$request->time           = $this->getRequestTime();
		$request->method         = $this->getRequestMethod();
		$request->uri            = $this->getRequestUri();
		$request->headers        = $this->getRequestHeaders();
		$request->getData        = $this->getGetData();
		$request->postData       = $this->getPostData();
		$request->sessionData    = $this->getSessionData();
		$request->cookies        = $this->getCookies();
		$request->responseStatus = $this->getResponseStatus();
		$request->responseTime   = $this->getResponseTime();

		return $request;
	}

	/**
	 * Return cookies (replace unserializable items, attempt to remove passwords)
	 */
	protected function getCookies()
	{
		return $this->removePasswords(
			$this->replaceUnserializable($_COOKIE)
		);
	}

	/**
	 * Return GET data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getGetData()
	{
		return $this->removePasswords(
			$this->replaceUnserializable($_GET)
		);
	}

	/**
	 * Return POST data (replace unserializable items, attempt to remove passwords)
	 */
	protected function getPostData()
	{
		return $this->removePasswords(
			$this->replaceUnserializable($_POST)
		);
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
		} else if (isset($_SERVER['REQUEST_TIME'])) {
			return $_SERVER['REQUEST_TIME'];
		}
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
	 * Return response status code (if available)
	 */
	protected function getResponseStatus()
	{
		if (!function_exists('http_response_code'))
			return null;

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
		if (!isset($_SESSION))
			return array();

		return $this->removePasswords(
			$this->replaceUnserializable($_SESSION)
		);
	}
}
