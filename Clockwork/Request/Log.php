<?php
namespace Clockwork\Request;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Data structure representing application log
 */
class Log extends AbstractLogger
{
	/**
	 * Array of log messages, with level and timestamp
	 */
	public $data = array();

	/**
	 * Add a new timestamped message, with an optional level
	 */
	public function log($level = LogLevel::INFO, $message, array $context = array())
	{
		if (is_object($message)) {
			if (method_exists($message, '__toString')) {
				$message = (string) $message;
			} else if (method_exists($message, 'toArray')) {
				$message = json_encode($message->toArray());
			} else {
				$message = json_encode((array) $message);
			}
		} else if (is_array($message)) {
			$message = json_encode($message);
		}

		$this->data[] = array(
			'message' => $message,
			'level' => $level,
			'time' => microtime(true),
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
