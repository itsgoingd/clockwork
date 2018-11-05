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
		$trace = StackTrace::get()->resolveViewName();
		$caller = $trace->firstNonVendor([ 'itsgoingd', 'laravel', 'slim', 'monolog' ]);

		$this->data[] = [
			'message'   => (new Serializer([ 'toString' => true ]))->normalize($message),
			'exception' => $this->formatException($context),
			'context'   => $this->formatContext($context),
			'level'     => $level,
			'time'      => microtime(true),
			'file'      => $caller ? $caller->shortPath : null,
			'line'      => $caller ? $caller->line : null,
			'trace'     => $caller && ($this->collectStackTraces || ! empty($context['trace']))
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

	// format message context, removes exception if we are serializing it
	protected function formatContext($context)
	{
		if ($this->hasException($context)) {
			unset($context['exception']);
		}

		return (new Serializer)->normalize($context);
	}

	// format exception if present in the context
	protected function formatException($context)
	{
		if ($this->hasException($context)) {
			return (new Serializer)->exception($context['exception']);
		}
	}

	// check if context has serializable exception
	protected function hasException($context)
	{
		return ! empty($context['exception']) && $context['exception'] instanceof \Exception && empty($context['raw']);
	}
}
