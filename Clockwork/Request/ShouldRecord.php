<?php namespace Clockwork\Request;

// Filter requests before recording
class ShouldRecord
{
	// Enable collecting of errors only (requests with 4xx or 5xx responses)
	protected $errorsOnly = false;
	// Enable collecting of slow requests only, slow response time threshold in ms
	protected $slowOnly = false;

	// Custom filter callback
	protected $callback;

	// Merge multiple settings from array
	public function merge(array $data = [])
	{
		foreach ($data as $key => $val) $this->$key = $val;
	}

	// Apply the filter to a request
	public function filter(Request $request)
	{
		return $this->passErrorsOnly($request)
			&& $this->passSlowOnly($request)
			&& $this->passCallback($request);
	}

	protected function passErrorsOnly(Request $request)
	{
		if (! $this->errorsOnly) return true;

		return 400 <= $request->responseStatus && $request->responseStatus <= 599;
	}

	protected function passSlowOnly(Request $request)
	{
		if (! $this->slowOnly) return true;

		return $request->getResponseDuration() >= $this->slowOnly;
	}

	protected function passCallback(Request $request)
	{
		if (! $this->callback) return true;

		return call_user_func($this->callback, $request);
	}

	// Fluent API
	public function __call($method, $parameters)
	{
		if (! count($parameters)) return $this->$method;

		$this->$method = count($parameters) ? $parameters[0] : true;

		return $this;
	}
}
