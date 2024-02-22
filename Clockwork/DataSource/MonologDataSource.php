<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Support\Monolog\Monolog2\ClockworkHandler as Monolog2ClockworkHandler;
use Clockwork\Support\Monolog\Monolog\ClockworkHandler;
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

		$handler=null;
		switch (\Monolog\Logger::API) {
			case 1:
				$handler=new ClockworkHandler($this->log);
				break;
			case 2:
				$handler=new Monolog2ClockworkHandler($this->log);
				break;
			default:
				// By default use the latest implementation of clockwork handler
				$handler=new Monolog2ClockworkHandler($this->log);
				break;
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
