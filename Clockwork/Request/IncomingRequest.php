<?php namespace Clockwork\Request;

// Incoming HTTP request
class IncomingRequest
{
	// Method
	public $method;
	// URI
	public $uri;

	// Headers
	public $headers;

	// GET and POST data
	public $input = [];
	// Cookies
	public $cookies = [];

	// Host
	public $host;

	public function __construct(array $data = [])
	{
		foreach ($data as $key => $val) $this->$key = $val;
	}

	// Returns a header value, or default if not set
	public function header($key, $default = null)
	{
		return $this->headers[$key] ?? $default;
	}

	// Returns an input value, or default if not set
	public function input($key, $default = null)
	{
		return $this->input[$key] ?? $default;
	}

	// Returns true, if HTTP host is one of the common domains used for local development
	public function hasLocalHost()
	{
		$segments = explode('.', $this->host);
		$tld = $segments[count($segments) - 1];

		return $this->host == '127.0.0.1'
			|| in_array($tld, [ 'localhost', 'local', 'test', 'wip' ]);
	}
}
