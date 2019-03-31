<?php namespace Clockwork\Helpers;

class StackFrame
{
	public $call;
	public $function;
	public $line;
	public $file;
	public $class;
	public $object;
	public $type;
	public $args = [];
	public $shortPath;
	public $vendor;

	public function __construct(array $data = [], $basePath = '', $vendorPath = '')
	{
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}

		$this->call = $this->formatCall();
		$this->shortPath = str_replace($basePath, '', $this->file);
		$this->vendor = strpos($this->file, $vendorPath) === 0
			? explode('/', str_replace($vendorPath, '', $this->file))[0] : null;
	}

	protected function formatCall()
	{
		if ($this->class) {
			return "{$this->class}{$this->type}{$this->function}()";
		} else {
			return "{$this->function}()";
		}
	}
}
