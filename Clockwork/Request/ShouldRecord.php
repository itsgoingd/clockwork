<?php namespace Clockwork\Request;

class ShouldRecord
{
	protected $errorsOnly = false;
	protected $slowOnly = false;

	protected $callback;

	public function errorsOnly($errorsOnly = true)
	{
		$this->errorsOnly = $errorsOnly;

		return $this;
	}

	public function slowOnly($slowOnly)
	{
		$this->slowOnly = $slowOnly;

		return $this;
	}

	public function call($callback)
	{
		$this->callback = $callback;

		return $this;
	}

	public function merge(array $data = [])
	{
		foreach ($data as $key => $val) $this->$key = $val;
	}

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

		return $this->callback($request);
	}
}
