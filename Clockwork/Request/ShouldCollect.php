<?php namespace Clockwork\Request;

// Filter incoming requests before collecting data
class ShouldCollect
{
	// Enable on-demand mode, boolean or the secret value
	protected $onDemand = false;
	// Enable sampling, chance to be sampled (eg. 100 to collect 1 in 100 requests)
	protected $sample = false;

	// List of URIs that should not be collected, can contain regexes
	protected $except = [];
	// List of URIs that should only be collected, can contain regexes (only used if non-empty)
	protected $only = [];

	// Disable collection of OPTIONS method requests (most commonly used for CORS pre-flight requests)
	protected $exceptPreflight = false;

	// Custom filter callback
	protected $callback;

	// Append one or more except URIs
	public function except($uris)
	{
		$this->except = array_merge($this->except, is_array($uris) ? $uris : [ $uris ]);

		return $this;
	}

	// Append one or more only URIs
	public function only($uris)
	{
		$this->only = array_merge($this->only, is_array($uris) ? $uris : [ $uris ]);

		return $this;
	}

	// Merge multiple settings from array
	public function merge(array $data = [])
	{
		foreach ($data as $key => $val) $this->$key = $val;
	}

	// Apply the filter to an incoming request
	public function filter(IncomingRequest $request)
	{
		return $this->passOnDemand($request)
			&& $this->passSampling()
			&& $this->passExcept($request)
			&& $this->passOnly($request)
			&& $this->passExceptPreflight($request)
			&& $this->passCallback($request);
	}

	protected function passOnDemand(IncomingRequest $request)
	{
		if (! $this->onDemand) return true;

		if ($this->onDemand !== true) {
			$input = $request->input['clockwork-profile'] ?? '';
			$cookie = $request->cookies['clockwork-profile'] ?? '';

			return hash_equals($this->onDemand, $input) || hash_equals($this->onDemand, $cookie);
		}

		return isset($request->input['clockwork-profile']) || isset($request->cookies['clockwork-profile']);
	}

	protected function passSampling()
	{
		if (! $this->sample) return true;

		return mt_rand(0, $this->sample) == $this->sample;
	}

	protected function passExcept(IncomingRequest $request)
	{
		if (! count($this->except)) return true;

		foreach ($this->except as $pattern) {
			if (preg_match('#' . str_replace('#', '\#', $pattern) . '#', $request->uri)) return false;
		}

		return true;
	}

	protected function passOnly(IncomingRequest $request)
	{
		if (! count($this->only)) return true;

		foreach ($this->only as $pattern) {
			if (preg_match('#' . str_replace('#', '\#', $pattern) . '#', $request->uri)) return true;
		}

		return false;
	}

	protected function passExceptPreflight(IncomingRequest $request)
	{
		if (! $this->exceptPreflight) return true;

		return strtoupper($request->method) != 'OPTIONS';
	}

	protected function passCallback(IncomingRequest $request)
	{
		if (! $this->callback) return true;

		return call_user_func($this->callback, $request);
	}

	public function __call($method, $parameters)
	{
		if (! count($parameters)) return $this->$method;

		$this->$method = count($parameters) ? $parameters[0] : true;

		return $this;
	}
}
