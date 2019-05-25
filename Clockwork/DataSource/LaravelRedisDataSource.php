<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Request;

use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

/**
 * Data source for Laravel redis component, provides redis queries
 */
class LaravelRedisDataSource extends DataSource
{
	/**
	 * Event dispatcher
	 */
	protected $eventDispatcher;

	/**
	 * Executed redis commands
	 */
	protected $commands = [];

	// Whether to skip Redis commands originating from Laravel cache Redis store
	protected $skipCacheCommands = true;

	/**
	 * Create a new data source instance, takes an event dispatcher as argument
	 */
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

	/**
	 * Start listening to redis events
	 */
	public function listenToEvents()
	{
		$this->eventDispatcher->listen(\Illuminate\Redis\Events\CommandExecuted::class, function ($event) {
			$this->registerCommand([
				'command'    => $event->command,
				'parameters' => $event->parameters,
				'duration'   => $event->time,
				'connection' => $event->connectionName
			]);
		});
	}

	/**
	 * Adds redis commands to the request
	 */
	public function resolve(Request $request)
	{
		$request->redisCommands = array_merge($request->redisCommands, $this->getCommands());

		return $request;
	}

	/**
	 * Registers a new command, resolves caller file and line no
	 */
	public function registerCommand(array $command)
	{
		$trace = StackTrace::get()->resolveViewName();

		$command = array_merge($command, [
			'trace' => $shortTrace = (new Serializer)->trace($trace),
			'file'  => isset($shortTrace[0]) ? $shortTrace[0]['file'] : null,
			'line'  => isset($shortTrace[0]) ? $shortTrace[0]['line'] : null
		]);

		if ($this->passesFilters([ $command, $trace ])) {
			$this->commands[] = $command;
		}
	}

	/**
	 * Returns an array of redis commands in Clockwork metadata format
	 */
	protected function getCommands()
	{
		return array_map(function ($query) {
			return array_merge($query, [
				'parameters' => isset($query['parameters']) ? (new Serializer)->normalize($query['parameters']) : null
			]);
		}, $this->commands);
	}
}
