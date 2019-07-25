<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;

class Search
{
	public $uri = [];
	public $controller = [];
	public $method = [];
	public $status = [];
	public $time = [];
	public $received = [];

	public function __construct($search = [])
	{
		foreach ([ 'uri', 'controller', 'method', 'status', 'time', 'received' ] as $condition) {
			$this->$condition = isset($search[$condition]) ? $search[$condition] : [];
		}

		$this->method = array_map('strtolower', $this->method);
	}

	public static function fromRequest($data = [])
	{
		return new static($data);
	}

	public function matches(Request $request)
	{
		return $this->matchesString($this->uri, $request->uri)
			&& $this->matchesString($this->controller, $request->controller)
			&& $this->matchesExact($this->method, strtolower($request->method))
			&& $this->matchesNumber($this->status, $request->responseStatus)
			&& $this->matchesNumber($this->time, $request->responseDuration)
			&& $this->matchesDate($this->received, $request->time);
	}

	public function isEmpty()
	{
		return ! count($this->uri) && ! count($this->controller) && ! count($this->method) && ! count($this->status)
			&& ! count($this->time) && ! count($this->received);
	}

	public function isNotEmpty()
	{
		return ! $this->isEmpty();
	}

	protected function matchesDate($inputs, $value)
	{
		if (! count($inputs)) return true;

		foreach ($inputs as $input) {
			if (preg_match('/^<(.+)$/', $input, $match)) {
				if ($value < strtotime($match[1])) return true;
			} elseif (preg_match('/^>(.+)$/', $input, $match)) {
				if ($value > strtotime($match[1])) return true;
			}
		}

		return false;
	}

	protected function matchesExact($inputs, $value)
	{
		if (! count($inputs)) return true;

		return in_array($value, $inputs);
	}

	protected function matchesNumber($inputs, $value)
	{
		if (! count($inputs)) return true;

		foreach ($inputs as $input) {
			if (preg_match('/^<(\d+(?:\.\d+)?)$/', $input, $match)) {
				if ($value < $match[1]) return true;
			} elseif (preg_match('/^>(\d+(?:\.\d+)?)$/', $input, $match)) {
				if ($value > $match[1]) return true;
			} elseif (preg_match('/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', $input, $match)) {
				if ($match[1] < $value && $value < $match[2]) return true;
			} else {
				if ($value == $input) return true;
			}
		}

		return false;
	}

	protected function matchesString($inputs, $value)
	{
		if (! count($inputs)) return true;

		foreach ($inputs as $input) {
			if (strpos($value, $input) !== false) return true;
		}

		return false;
	}
}
