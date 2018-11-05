<?php namespace Clockwork\Helpers;

class StackTrace
{
	use Concerns\ResolvesViewName;

	protected $frames;

	protected $basePath;
	protected $vendorPath;

	public static function get()
	{
		return static::from(
			debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS)
		);
	}

	public static function from(array $trace)
	{
		$basePath = static::resolveBasePath();
		$vendorPath = static::resolveVendorPath();

		return new static(array_map(function ($frame) use ($basePath, $vendorPath) {
			return new StackFrame($frame, $basePath, $vendorPath);
		}, $trace), $basePath, $vendorPath);
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

	public function framesBefore(StackFrame $frame)
	{
		return new static(
			array_slice($this->frames, array_search($frame, $this->frames) + 1),
			$this->basePath,
			$this->vendorPath
		);
	}

	public function first(callable $callback)
	{
		foreach ($this->frames as $frame) {
			if ($callback($frame)) return $frame;
		}
	}

	public function firstNonVendor(array $ignoredPackages = null)
	{
		$ignoredPaths = $this->getIgnoredNonVendorCallerPaths($ignoredPackages);

		return $this->first(function ($frame) use ($ignoredPaths) {
			return $frame->file && ! $this->isSubdir($frame->file, $ignoredPaths);
		});
	}

	protected function getIgnoredNonVendorCallerPaths(array $ignoredPackages = null)
	{
		if (! $ignoredPackages) {
			return [ $this->vendorPath ];
		}

		return array_map(function ($ignoredPackage) {
			return "{$this->vendorPath}{$ignoredPackage}";
		}, $ignoredPackages);
	}

	protected function isSubdir($subdir, array $paths)
	{
		foreach ($paths as $path) {
			if (strpos($subdir, $path) === 0) {
				return true;
			}
		}

		return false;
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
