<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\{Log, Request};
use Clockwork\Support\Monolog;

use Monolog\Logger;

// Data source for Monolog, provides application log
class MonologDataSource extends DataSource
{
	// Clockwork log instance
	protected $log;

	// Create a new data source, takes Monolog instance as an argument
	public function __construct(Logger $monolog)
	{
		$this->log = new Log;

		if (Logger::API === 1) {
			$handler = new Monolog\Monolog\ClockworkHandler($this->log);
		} elseif (Logger::API === 2) {
			$handler = new Monolog\Monolog2\ClockworkHandler($this->log);
		} else {
			$handler = new Monolog\Monolog3\ClockworkHandler($this->log);
		}

		$monolog->pushHandler($handler);
	}

	// Adds log entries to the request
	public function resolve(Request $request)
	{
		$request->log()->merge($this->log);

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->log = new Log;
	}
}
