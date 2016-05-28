<?php namespace Clockwork\Helpers;

class StackFrame
{
	public $function;
	public $line;
	public $file;
	public $class;
	public $object;
	public $type;
	public $args = [];
	public $shortPath;

	public function __construct(array $data = array(), $basePath = '')
	{
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}

		$this->shortPath = str_replace($basePath, '', $this->file);
	}
}
