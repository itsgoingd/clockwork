<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Support\Monolog\Monolog2\ClockworkHandler;
use Clockwork\Support\Monolog\Monolog\ClockworkHandler as LegacyClockworkHandler;
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

		$handler = Logger::API == 1 ? new LegacyClockworkHandler($this->log) : new ClockworkHandler($this->log);
		
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
