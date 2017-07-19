<?php namespace Clockwork\Request;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;

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
	public $data = [];

	/**
	 * Add a new timestamped message, with an optional level
	 */
	public function log($level = LogLevel::INFO, $message, array $context = [])
	{
		$caller = StackTrace::get()->firstNonVendor([ 'itsgoingd', 'laravel', 'slim', 'monolog' ]);

		$this->data[] = [
			'message' => Serializer::simplify($message, 3, [ 'toString' => true ]),
			'context' => Serializer::simplify($context),
			'level'   => $level,
			'time'    => microtime(true),
			'file'    => $caller->shortPath,
			'line'    => $caller->line
		];
	}

	/**
	 * Return log data as an array
	 */
	public function toArray()
	{
		return $this->data;
	}
}
