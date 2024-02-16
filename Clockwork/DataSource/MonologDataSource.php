<?php namespace Clockwork\DataSource;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Support\Monolog\Monolog2\ClockworkHandler;
use Clockwork\Support\Monolog\Monolog\ClockworkHandler as MonologClockworkHandler;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger as Monolog;
use ReflectionMethod;

// Data source for Monolog, provides application log
class MonologDataSource extends DataSource
{
	// Clockwork log instance
	protected $log;

	// Create a new data source, takes Monolog instance as an argument
	public function __construct(Monolog $monolog)
	{
		$this->log = new Log;

		/**
		 * Use `Clockwork\Support\Monolog\Monolog2\ClockworkHandler`
		 * when the method `AbstractProcessingHandler::write` has a return type
		 * Because Monolog 2 introduced scalar type hints and return hints
		 */
		$writeMethod = new ReflectionMethod(AbstractProcessingHandler::class, 'write');
		if ($writeMethod->hasReturnType()) {
			$monolog->pushHandler(new ClockworkHandler($this->log));
		} else {
			$monolog->pushHandler(new MonologClockworkHandler($this->log));
		}
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
