<?php
namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Support\Monolog\Handler\ClockworkHandler;
use Monolog\Logger as Monolog;

/**
 * Data source for Monolog, provides application log
 */
class MonologDataSource extends DataSource
{
	/**
	 * Log data structure
	 */
	protected $log;

	/**
	 * Create a new data source, takes Laravel application instance as an argument
	 */
	public function __construct(Monolog $monolog)
	{
		$this->log = new Log();

		$monolog->pushHandler(new ClockworkHandler($this->log));
	}

	/**
	 * Adds log entries to the request
	 */
	public function resolve(Request $request)
	{
		$request->log = array_merge($request->log, $this->log->toArray());

		return $request;
	}
}
