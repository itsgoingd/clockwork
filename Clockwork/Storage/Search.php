<?php namespace Clockwork\Storage;

use Clockwork\Request\{Request, RequestType};

// Rules for searching requests
class Search
{
	// Search parameters
	public $uri = [];
	public $controller = [];
	public $method = [];
	public $status = [];
	public $time = [];
	public $received = [];
	public $name = [];
	public $type = [];

	// Whether to stop search on the first not matching request
	public $stopOnFirstMismatch = false;

	// Create a new instance, takes search parameters and additional options
	public function __construct($search = [], $options = [])
	{
		foreach ([ 'uri', 'controller', 'method', 'status', 'time', 'received', 'name', 'type' ] as $condition) {
			$this->$condition = $search[$condition] ?? [];
		}

		foreach ([ 'stopOnFirstMismatch' ] as $option) {
			$this->$option = $options[$option] ?? $this->$option;
		}

		$this->method = array_map('strtolower', $this->method);
	}

	// Create a new instance from request input
	public static function fromRequest($data = [])
	{
		return new static($data);
	}

	// Check whether the request matches current search parameters
	public function matches(Request $request)
	{
		if ($request->type == RequestType::COMMAND) {
			return $this->matchesCommand($request);
		} elseif ($request->type == RequestType::QUEUE_JOB) {
			return $this->matchesQueueJob($request);
		} elseif ($request->type == RequestType::TEST) {
			return $this->matchesTest($request);
		} else {
			return $this->matchesRequest($request);
		}
	}

	// Check whether a request type request matches
	protected function matchesRequest(Request $request)
	{
		return $this->matchesString($this->type, RequestType::REQUEST)
			&& $this->matchesString($this->uri, $request->uri)
			&& $this->matchesString($this->controller, $request->controller)
			&& $this->matchesExact($this->method, strtolower($request->method))
			&& $this->matchesNumber($this->status, $request->responseStatus)
			&& $this->matchesNumber($this->time, $request->responseDuration)
			&& $this->matchesDate($this->received, $request->time);
	}

	// Check whether a command type request matches
	protected function matchesCommand(Request $request)
	{
		return $this->matchesString($this->type, RequestType::COMMAND)
			&& $this->matchesString($this->name, $request->commandName)
			&& $this->matchesNumber($this->status, $request->commandExitCode)
			&& $this->matchesNumber($this->time, $request->responseDuration)
			&& $this->matchesDate($this->received, $request->time);
	}

	// Check whether a queue-job type request matches
	protected function matchesQueueJob(Request $request)
	{
		return $this->matchesString($this->type, RequestType::QUEUE_JOB)
			&& $this->matchesString($this->name, $request->jobName)
			&& $this->matchesString($this->status, $request->jobStatus)
			&& $this->matchesNumber($this->time, $request->responseDuration)
			&& $this->matchesDate($this->received, $request->time);
	}

	// Check whether a test type request matches
	protected function matchesTest(Request $request)
	{
		return $this->matchesString($this->type, RequestType::TEST)
			&& $this->matchesString($this->name, $request->testName)
			&& $this->matchesString($this->status, $request->testStatus)
			&& $this->matchesNumber($this->time, $request->responseDuration)
			&& $this->matchesDate($this->received, $request->time);
	}

	// Check if there are no search parameters specified
	public function isEmpty()
	{
		return ! count($this->uri) && ! count($this->controller) && ! count($this->method) && ! count($this->status)
			&& ! count($this->time) && ! count($this->received) && ! count($this->name) && ! count($this->type);
	}

	// Check if there are some search parameters specified
	public function isNotEmpty()
	{
		return ! $this->isEmpty();
	}

	// Check if the value matches date type search parameter
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

	// Check if the value matches exact type search parameter
	protected function matchesExact($inputs, $value)
	{
		if (! count($inputs)) return true;

		return in_array($value, $inputs);
	}

	// Check if the value matches number type search parameter
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

	// Check if the value matches string type search parameter
	protected function matchesString($inputs, $value)
	{
		if (! count($inputs)) return true;

		foreach ($inputs as $input) {
			if (strpos($value, $input) !== false) return true;
		}

		return false;
	}
}
