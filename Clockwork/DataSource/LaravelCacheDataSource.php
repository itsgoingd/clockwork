<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Request;

use Illuminate\Events\Dispatcher as EventDispatcher;

/**
 * Data source for Laravel cache component, provides cache queries and stats
 */
class LaravelCacheDataSource extends DataSource
{
	/**
	 * Event dispatcher
	 */
	protected $eventDispatcher;

	/**
	 * Executed cache queries
	 */
	protected $queries = [];

	/**
	 * Create a new data source instance, takes an event dispatcher as argument
	 */
	public function __construct(EventDispatcher $eventDispatcher)
	{
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * Start listening to cache events
	 */
	public function listenToEvents()
	{
		if (! class_exists('Illuminate\Cache\Events\CacheHit')) {
			// legacy Laravel 5.1 style events
			$this->eventDispatcher->listen('cache.hit', function ($key, $value) {
				$this->registerQuery([ 'type' => 'hit', 'key' => $key, 'value' => $value ]);
			});
			$this->eventDispatcher->listen('cache.missed', function ($key) {
				$this->registerQuery([ 'type' => 'miss', 'key' => $key ]);
			});
			$this->eventDispatcher->listen('cache.write', function ($key, $value, $minutes) {
				$this->registerQuery([
					'type' => 'write', 'key' => $key, 'value' => $value, 'expiration' => $minutes * 60
				]);
			});
			$this->eventDispatcher->listen('cache.delete', function ($key) {
				$this->registerQuery([ 'type' => 'delete', 'key' => $key ]);
			});

			return;
		}

		$this->eventDispatcher->listen('Illuminate\Cache\Events\CacheHit', function ($event) {
			$this->registerQuery([ 'type' => 'hit', 'key' => $event->key, 'value' => $event->value ]);
		});
		$this->eventDispatcher->listen('Illuminate\Cache\Events\CacheMissed', function ($event) {
			$this->registerQuery([ 'type' => 'miss', 'key' => $event->key ]);
		});
		$this->eventDispatcher->listen('Illuminate\Cache\Events\KeyWritten', function ($event) {
			$this->registerQuery([
				'type' => 'write', 'key' => $event->key, 'value' => $event->value, 'expiration' => $event->minutes * 60
			]);
		});
		$this->eventDispatcher->listen('Illuminate\Cache\Events\KeyForgotten', function ($event) {
			$this->registerQuery([ 'type' => 'delete', 'key' => $event->key ]);
		});
	}

	/**
	 * Adds cache queries and stats to the request
	 */
	public function resolve(Request $request)
	{
		$request->cacheQueries = array_merge($request->cacheQueries, $this->getQueries());
		$request->cacheReads = $request->cacheReads + $this->getCacheReads();
		$request->cacheHits = $request->cacheHits + $this->getCacheHits();
		$request->cacheWrites = $request->cacheWrites + $this->getCacheWrites();
		$request->cacheDeletes = $request->cacheDeletes + $this->getCacheDeletes();

		return $request;
	}

	/**
	 * Registers a new query, resolves caller file and line no
	 */
	public function registerQuery(array $query)
	{
		$caller = StackTrace::get()->firstNonVendor([ 'itsgoingd', 'laravel', 'illuminate' ]);

		$this->queries[] = array_merge($query, [
			'file' => $caller->shortPath,
			'line' => $caller->line
		]);
	}

	/**
	 * Returns an array of cache queries in Clockwork metadata format
	 */
	protected function getQueries()
	{
		return array_map(function ($query) {
			return array_merge($query, [
				'connection' => null,
				'time' => null,
				'value' => isset($query['value']) ? Serializer::simplify($query['value']) : null
			]);
		}, $this->queries);
	}

	/**
	 * Returns a number of cache reads (hits + misses)
	 */
	protected function getCacheReads()
	{
		return count(array_filter($this->queries, function ($query) {
			return $query['type'] == 'hit' || $query['type'] == 'miss';
		}));
	}

	/**
	 * Returns a number of cache hits
	 */
	protected function getCacheHits()
	{
		return count(array_filter($this->queries, function ($query) {
			return $query['type'] == 'hit';
		}));
	}

	/**
	 * Returns a number of cache writes
	 */
	protected function getCacheWrites()
	{
		return count(array_filter($this->queries, function ($query) {
			return $query['type'] == 'write';
		}));
	}

	/**
	 * Returns a number of cache deletes
	 */
	protected function getCacheDeletes()
	{
		return count(array_filter($this->queries, function ($query) {
			return $query['type'] == 'delete';
		}));
	}
}
