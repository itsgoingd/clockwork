<?php namespace Clockwork\Request;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;
use Clockwork\Helpers\StackFilter;

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

	// Create a new timeline, optionally with existing events
	public function __construct($data = [])
	{
		$this->data = $data;
	}

	/**
	 * Add a new timestamped message, with a level and context, context can be used to override serializer defaults
	 * $context['trace'] = true can be used to force collecting a stack trace
	 */
	public function log($level = LogLevel::INFO, $message, array $context = [])
	{
		$trace = $this->hasTrace($context) ? $context['trace'] : StackTrace::get()->resolveViewName();

		$this->data[] = [
			'message'   => (new Serializer($context))->normalize($message),
			'exception' => $this->formatException($context),
			'context'   => $this->formatContext($context),
			'level'     => $level,
			'time'      => microtime(true),
			'trace'     => (new Serializer(! empty($context['trace']) ? [ 'traces' => true ] : []))->trace($trace)
		];
	}

	// Merge another log instance into the current log
	public function merge(Log $log)
	{
		$this->data = array_merge($this->data, $log->data);

		return $this;
	}

	// Sort the log messages by start time
	public function sort()
	{
		uasort($this->data, function ($a, $b) { return $a['time'] * 1000 - $b['time'] * 1000; });
	}

	/**
	 * Return log data as an array
	 */
	public function toArray()
	{
		return $this->data;
	}

	// format message context, removes exception and trace if we are serializing them
	protected function formatContext($context)
	{
		if ($this->hasException($context)) unset($context['exception']);
		if ($this->hasTrace($context)) unset($context['trace']);

		return (new Serializer)->normalize($context);
	}

	// format exception if present in the context
	protected function formatException($context)
	{
		if ($this->hasException($context)) {
			return (new Serializer)->exception($context['exception']);
		}
	}

	// check if context has serializable trace
	protected function hasTrace($context)
	{
		return ! empty($context['trace']) && $context['trace'] instanceof StackTrace && empty($context['raw']);
	}

	// check if context has serializable exception
	protected function hasException($context)
	{
		return ! empty($context['exception']) && $context['exception'] instanceof \Exception && empty($context['raw']);
	}
}
