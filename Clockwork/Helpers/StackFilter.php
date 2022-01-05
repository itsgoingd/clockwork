<?php namespace Clockwork\Helpers;

// Filter stack traces
class StackFilter
{
	protected $classes = [];
	protected $notClasses = [];

	protected $files = [];
	protected $notFiles = [];

	protected $functions = [];
	protected $notFunctions = [];

	protected $namespaces = [];
	protected $notNamespaces = [];

	protected $vendors = [];
	protected $notVendors = [];

	public static function make()
	{
		return new static;
	}

	public function isClass($classes)
	{
		$this->classes = array_merge($this->classes, is_array($classes) ? $classes : [ $classes ]);
		return $this;
	}

	public function isNotClass($classes)
	{
		$this->notClasses = array_merge($this->notClasses, is_array($classes) ? $classes : [ $classes ]);
		return $this;
	}

	public function isFile($files)
	{
		$this->files = array_merge($this->files, is_array($files) ? $files : [ $files ]);
		return $this;
	}

	public function isNotFile($files)
	{
		$this->notFiles = array_merge($this->notFiles, is_array($files) ? $files : [ $files ]);
		return $this;
	}

	public function isFunction($functions)
	{
		$this->functions = array_merge($this->functions, is_array($functions) ? $functions : [ $functions ]);
		return $this;
	}

	public function isNotFunction($functions)
	{
		$this->notFunctions = array_merge($this->notFunctions, is_array($functions) ? $functions : [ $functions ]);
		return $this;
	}

	public function isNamespace($namespaces)
	{
		$this->namespaces = array_merge($this->namespaces, is_array($namespaces) ? $namespaces : [ $namespaces ]);
		return $this;
	}

	public function isNotNamespace($namespaces)
	{
		$this->notNamespaces = array_merge($this->notNamespaces, is_array($namespaces) ? $namespaces : [ $namespaces ]);
		return $this;
	}

	public function isVendor($vendors)
	{
		$this->vendors = array_merge($this->vendors, is_array($vendors) ? $vendors : [ $vendors ]);
		return $this;
	}

	public function isNotVendor($vendors)
	{
		$this->notVendors = array_merge($this->notVendors, is_array($vendors) ? $vendors : [ $vendors ]);
		return $this;
	}

	// Apply the filter to a stack frame
	public function filter(StackFrame $frame)
	{
		return $this->matchesClass($frame)
			&& $this->matchesFile($frame)
			&& $this->matchesFunction($frame)
			&& $this->matchesNamespace($frame)
			&& $this->matchesVendor($frame);
	}

	// Return a closure calling this filter
	public function closure()
	{
		return function ($frame) { return $this->filter($frame); };
	}

	protected function matchesClass(StackFrame $frame)
	{
		if (count($this->classes) && ! in_array($frame->class, $this->classes)) return false;
		if (count($this->notClasses) && in_array($frame->class, $this->notClasses)) return false;

		return true;
	}

	protected function matchesFile(StackFrame $frame)
	{
		if (count($this->files) && ! in_array($frame->file, $this->files)) return false;
		if (count($this->notFiles) && in_array($frame->file, $this->notFiles)) return false;

		return true;
	}

	protected function matchesFunction(StackFrame $frame)
	{
		if (count($this->functions) && ! in_array($frame->function, $this->functions)) return false;
		if (count($this->notFunctions) && in_array($frame->function, $this->notFunctions)) return false;

		return true;
	}

	protected function matchesNamespace(StackFrame $frame)
	{
		foreach ($this->notNamespaces as $namespace) {
			if ($frame->class !== null && strpos($frame->class, "{$namespace}\\") !== false) return false;
		}

		if (! count($this->namespaces)) return true;

		foreach ($this->namespaces as $namespace) {
			if ($frame->class !== null && strpos($frame->class, "{$namespace}\\") !== false) return true;
		}

		return false;
	}

	protected function matchesVendor(StackFrame $frame)
	{
		if (count($this->vendors) && ! in_array($frame->vendor, $this->vendors)) return false;
		if (count($this->notVendors) && in_array($frame->vendor, $this->notVendors)) return false;

		return true;
	}
}
