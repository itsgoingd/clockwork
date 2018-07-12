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

	// Whether the log messages should have stack traces
	protected $collectStackTraces;

	/**
	 * Add a new timestamped message, with a level and context,
	 * $context['trace'] = true can be used to force collecting a stack trace
	 */
	public function log($level = LogLevel::INFO, $message, array $context = [])
	{
		$trace = StackTrace::get();
		$caller = $trace->firstNonVendor([ 'itsgoingd', 'laravel', 'slim', 'monolog' ]);

		$this->data[] = [
			'message' => (new Serializer([ 'toString' => true ]))->normalize($message),
			'context' => (new Serializer)->normalize($context),
			'level'   => $level,
			'time'    => microtime(true),
			'file'    => $caller->shortPath,
			'line'    => $caller->line,
			'trace'   => $this->collectStackTraces || ! empty($context['trace'])
				? (new Serializer)->trace($trace->framesBefore($caller)) : null
		];
	}

	/**
	 * Return log data as an array
	 */
	public function toArray()
	{
		return $this->data;
	}

	// Enable or disable collecting of stack traces
	public function collectStackTraces($enable = true)
	{
		$this->collectStackTraces = $enable;
		return $this;
	}
}
