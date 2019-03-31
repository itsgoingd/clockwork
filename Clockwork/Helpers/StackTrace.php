<?php namespace Clockwork\Helpers;

class StackTrace
{
	use Concerns\ResolvesViewName;

	protected $frames;

	protected $basePath;
	protected $vendorPath;

	protected static $defaults = [
		'raw'    => false,
		'filter' => null,
		'skip'   => null,
		'limit'  => null
	];

	public static function get($options = [])
	{
		return static::from(
			debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS)
		);
	}

	public static function raw()
	{
		return static::get([ 'raw' => true ]);
	}

	public static function from(array $trace, $options = [])
	{
		$basePath = static::resolveBasePath();
		$vendorPath = static::resolveVendorPath();
		$options = $options + static::$defaults;

		$trace = new static(array_map(function ($frame) use ($basePath, $vendorPath) {
			return new StackFrame($frame, $basePath, $vendorPath);
		}, $trace), $basePath, $vendorPath);

		if (! $options['raw']) {
			if ($options['filter']) $trace = $trace->filter($options['filter']);
			if ($options['skip']) $trace = $trace->skip($options['skip']);
			if ($options['limit']) $trace = $trace->limit($options['limit']);
		}

		return $trace;
	}

	// set default options for all captured stack traces
	public static function defaults(array $defaults)
	{
		static::$defaults = $defaults + static::$defaults;
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

	public function filter($filter)
	{
		if ($filter instanceof StackFilter) $filter = $filter->closure();

		return $this->copy(array_filter($filter, $this->frames));
	}

	public function skip($count)
	{
		if ($count instanceof StackFilter) $count = $count->closure();
		if ($count instanceof \Closure) $count = array_search($this->first($count), $this->frames);

		return $this->copy(array_slice($this->frames, $count));
	}

	public function limit($count)
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
}
