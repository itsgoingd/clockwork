<?php namespace Clockwork\Request;

// Incoming HTTP request
class IncomingRequest
{
	// Method
	public $method;
	// URI
	public $uri;

	// GET and POST data
	public $input = [];
	// Cookies
	public $cookies = [];

	public function __construct(array $data = [])
	{
		foreach ($data as $key => $val) $this->$key = $val;
	}
}
