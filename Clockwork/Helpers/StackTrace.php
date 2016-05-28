<?php namespace Clockwork\Helpers;

class StackTrace
{
	protected $basePath;

	protected $backtrace;

	public static function get()
	{
		return new static;
	}

	public function first(callable $callback)
	{
		foreach ($this->getBacktrace() as $frame) {
			$frame = new StackFrame($frame, $this->getBasePath());

			if ($callback($frame)) {
				return $frame;
			}
		}
	}

	public function firstNonVendor(array $ignoredPackages = null)
	{
		$ignoredPaths = $this->getIgnoredNonVendorCallerPaths($ignoredPackages);

		return $this->first(function($frame) use($ignoredPaths) {
			return $frame->file && ! $this->isSubdir($frame->file, $ignoredPaths);
		});
	}

	protected function getBacktrace()
	{
		if (! $this->backtrace) {
			$this->backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
		}

		return $this->backtrace;
	}

	protected function getBasePath()
	{
		if (! $this->basePath) {
			$vendorDir = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
			$this->basePath = substr(__DIR__, 0, strpos(__DIR__, $vendorDir));
		}

		return $this->basePath;
	}

	protected function getVendorPath()
	{
		return $this->getBasePath() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
	}

	protected function getIgnoredNonVendorCallerPaths(array $ignoredPackages = null)
	{
		$vendorPath = $this->getVendorPath();

		if (! $ignoredPackages) {
			return array($vendorPath);
		}

		$ignoredPaths = array();

		foreach ($ignoredPackages as $ignoredPackage) {
			$ignoredPaths[] = "{$vendorPath}{$ignoredPackage}";
		}

		return $ignoredPaths;
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
}
