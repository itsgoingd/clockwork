<?php namespace Clockwork\Helpers;

class StackTrace
{
	use Concerns\ResolvesViewName;

	protected $frames;

	protected $basePath;
	protected $vendorPath;

	public static function get($options = [])
	{
		$backtraceOptions = isset($options['arguments'])
			? DEBUG_BACKTRACE_PROVIDE_OBJECT : DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS;

		return static::from(debug_backtrace($backtraceOptions));
	}

	public static function from(array $trace)
	{
		$basePath = static::resolveBasePath();
		$vendorPath = static::resolveVendorPath();

		return new static(array_map(function ($frame, $index) use ($basePath, $vendorPath, $trace) {
			return new StackFrame(
				static::fixCallUserFuncFrame($frame, $trace, $index), $basePath, $vendorPath
			);
		}, $trace, array_keys($trace)), $basePath, $vendorPath);
	}

	public function __construct(array $frames, $basePath, $vendorPath)
	{
		$this->frames = $frames;
		$this->basePath = $basePath;
		$this->vendorPath = $vendorPath;
	}

	public function frames()
	{
		return $this->frames;
	}

	public function first($filter = null)
	{
		if (! $filter) return reset($this->frames);

		if ($filter instanceof StackFilter) $filter = $filter->closure();

		foreach ($this->frames as $frame) {
			if ($filter($frame)) return $frame;
		}
	}

	public function filter($filter = null)
	{
		if ($filter instanceof StackFilter) $filter = $filter->closure();

		return $this->copy(array_filter($this->frames, $filter));
	}

	public function skip($count = null)
	{
		if ($count instanceof StackFilter) $count = $count->closure();
		if ($count instanceof \Closure) $count = array_search($this->first($count), $this->frames);

		return $this->copy(array_slice($this->frames, $count));
	}

	public function limit($count = null)
	{
		return $this->copy(array_slice($this->frames, 0, $count));
	}

	public function copy($frames = null)
	{
		return new static($frames ?: $this->frames, $this->basePath, $this->vendorPath);
	}

	protected static function resolveBasePath()
	{
		return substr(__DIR__, 0, strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR));
	}

	protected static function resolveVendorPath()
	{
		return static::resolveBasePath() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
	}

	protected static function fixCallUserFuncFrame($frame, array $trace, $index)
	{
		if (isset($frame['file'])) return $frame;

		$nextFrame = isset($trace[$index + 1]) ? $trace[$index + 1] : null;

		if (! $nextFrame || ! in_array($nextFrame['function'], [ 'call_user_func', 'call_user_func_array' ])) return $frame;

		$frame['file'] = $nextFrame['file'];
		$frame['line'] = $nextFrame['line'];

		return $frame;
	}
}
