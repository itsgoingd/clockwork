<?php namespace Clockwork\Request;

class IncomingRequest
{
	public $method;
	public $uri;

	public $input = [];
	public $cookies = [];

	public function __construct(array $data = [])
	{
		foreach ($data as $key => $val) $this->$key = $val;
	}
}
