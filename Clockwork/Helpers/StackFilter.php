<?php namespace Clockwork\Helpers;

class StackFilter
{
	protected $classes = [];
	protected $notClasses = [];

	protected $files = [];
	protected $notFiles = [];

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

	public function filter(StackFrame $frame)
	{
		if (count($this->classes) && ! in_array($frame->class, $this->classes)) return false;
		if (count($this->notClasses) && in_array($frame->class, $this->notClasses)) return false;

		if (count($this->files) && ! in_array($frame->file, $this->files)) return false;
		if (count($this->notFiles) && in_array($frame->file, $this->notFiles)) return false;

		if (count($this->vendors) && ! in_array($frame->vendor, $this->vendors)) return false;
		if (count($this->notVendors) && in_array($frame->vendor, $this->notVendors)) return false;

		return true;
	}

	public function closure()
	{
		return function ($frame) { return $this->filter($frame); };
	}
}
