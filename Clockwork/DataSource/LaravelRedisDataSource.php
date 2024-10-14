<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\{Serializer, StackTrace};
use Clockwork\Request\Request;

use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

// Data source for Laravel redis component, provides redis commands
class LaravelRedisDataSource extends DataSource
{
	// Event dispatcher instance
	protected $eventDispatcher;

	// Executed redis commands
	protected $commands = [];

	// Whether to skip Redis commands originating from Laravel cache Redis store
	protected $skipCacheCommands = true;

	// Create a new data source instance, takes an event dispatcher and additional options as arguments
	public function __construct(EventDispatcher $eventDispatcher, $skipCacheCommands = true)
	{
		$this->eventDispatcher = $eventDispatcher;

		$this->skipCacheCommands = $skipCacheCommands;

		if ($this->skipCacheCommands) {
			$this->addFilter(function ($command, $trace) {
				return ! $trace->first(function ($frame) { return $frame->class == 'Illuminate\Cache\RedisStore'; });
			});
		}
	}

	// Adds redis commands to the request
	public function resolve(Request $request)
	{
		$request->redisCommands = array_merge($request->redisCommands, $this->getCommands());

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->commands = [];
	}

	// Listen to the cache events
	public function listenToEvents()
	{
		$this->eventDispatcher->listen(\Illuminate\Redis\Events\CommandExecuted::class, function ($event) {
			$this->registerCommand([
				'command'    => $event->command,
				'parameters' => $event->parameters,
				'duration'   => $event->time,
				'connection' => $event->connectionName,
				'time'       => microtime(true) - $event->time / 1000
			]);
		});
	}

	// Collect an executed command
	protected function registerCommand(array $command)
	{
		$trace = StackTrace::get()->resolveViewName();

		$command = array_merge($command, [
			'trace' => (new Serializer)->trace($trace)
		]);

		if ($this->passesFilters([ $command, $trace ])) {
			$this->commands[] = $command;
		}
	}

	// Get an array of executed redis commands
	protected function getCommands()
	{
		return array_map(function ($query) {
			return array_merge($query, [
				'parameters' => isset($query['parameters']) ? (new Serializer)->normalize($query['parameters']) : null
			]);
		}, $this->commands);
	}
}
