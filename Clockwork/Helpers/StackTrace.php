<?php namespace Clockwork\Helpers;

// A stack trace
class StackTrace
{
	use Concerns\ResolvesViewName;

	protected $frames;

	protected $basePath;
	protected $vendorPath;

	// Capture a new stack trace, accepts an array of options, "arguments" to include arguments in the trace and "limit"
	// to limit the trace length
	public static function get($options = [])
	{
		$backtraceOptions = isset($options['arguments'])
			? DEBUG_BACKTRACE_PROVIDE_OBJECT : DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS;
		$limit = isset($options['limit']) ? $options['limit'] : 0;

		return static::from(debug_backtrace($backtraceOptions, $limit));
	}

	// Create a stack trace from an existing debug_backtrace output
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

	// Get all frames
	public function frames()
	{
		return $this->frames;
	}

	// Get the first frame, optionally filtered by a stack filter or a closure
	public function first($filter = null)
	{
		if (! $filter) return reset($this->frames);

		if ($filter instanceof StackFilter) $filter = $filter->closure();

		foreach ($this->frames as $frame) {
			if ($filter($frame)) return $frame;
		}
	}

	// Get the last frame, optionally filtered by a stack filter or a closure
	public function last($filter = null)
	{
		if (! $filter) return $this->frames[count($this->frames) - 1];

		if ($filter instanceof StackFilter) $filter = $filter->closure();

		foreach (array_reverse($this->frames) as $frame) {
			if ($filter($frame)) return $frame;
		}
	}

	// Get trace filtered by a stack filter or a closure
	public function filter($filter = null)
	{
		if ($filter instanceof StackFilter) $filter = $filter->closure();

		return $this->copy(array_values(array_filter($this->frames, $filter)));
	}

	// Get trace skipping a number of frames or frames matching a stack filter or a closure
	public function skip($count = null)
	{
		if ($count instanceof StackFilter) $count = $count->closure();
		if ($count instanceof \Closure) $count = array_search($this->first($count), $this->frames);

		return $this->copy(array_slice($this->frames, $count));
	}

	// Get trace with a number of frames from the top
	public function limit($count = null)
	{
		return $this->copy(array_slice($this->frames, 0, $count));
	}

	// Get a copy of the trace
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

	// Fixes call_user_func stack frames missing file and line
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
