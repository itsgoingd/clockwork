<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Support\Monolog\Handler\ClockworkHandler;

use Monolog\Logger as Monolog;

// Data source for Monolog, provides application log
class MonologDataSource extends DataSource
{
	// Clockwork log instance
	protected $log;

	// Create a new data source, takes Monolog instance as an argument
	public function __construct(Monolog $monolog)
	{
		$this->log = new Log;

		$monolog->pushHandler(new ClockworkHandler($this->log));
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
