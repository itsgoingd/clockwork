<?php
namespace Clockwork\Request;

/**
 * Data structure representing application log
 */
class Log
{
	/**
	 * Available log levels
	 */
	const DEBUG = 1;
	const INFO = 2;
	const NOTICE = 3;
	const WARNING = 4;
	const ERROR = 5;

	/**
	 * Array of log messages, with level and timestamp
	 */
	public $data = array();

	/**
	 * Add a new timestamped message, with an optional level
	 */
	public function log($message, $level = Log::INFO)
	{
		$this->data[] = array(
			'message' => $message,
			'level' => $level,
			'time' => time(),
		);
	}

	/**
	 * Return log data as an array
	 */
	public function toArray()
	{
		return $this->data;
	}
}
