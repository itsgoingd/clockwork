<?php namespace Clockwork\DataSource;

use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Request;

use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

// Data source for Laravel cache component, provides cache queries and stats
class LaravelCacheDataSource extends DataSource
{
	// Event dispatcher instance
	protected $eventDispatcher;

	// Executed cache queries
	protected $queries = [];

	// Query counts by type
	protected $count = [
		'read' => 0, 'hit' => 0, 'write' => 0, 'delete' => 0
	];

	// Whether we are collecting cache queries or stats only
	protected $collectQueries = true;

	// Whether we are collecting values from cache queries
	protected $collectValues = true;

	// Create a new data source instance, takes an event dispatcher and additional options as argument
	public function __construct(EventDispatcher $eventDispatcher, $collectQueries = true, $collectValues = true)
	{
		$this->eventDispatcher = $eventDispatcher;

		$this->collectQueries = $collectQueries;
		$this->collectValues = $collectValues;
	}

	// Adds cache queries and stats to the request
	public function resolve(Request $request)
	{
		$request->cacheQueries = array_merge($request->cacheQueries, $this->queries);

		$request->cacheReads   += $this->count['read'];
		$request->cacheHits    += $this->count['hit'];
		$request->cacheWrites  += $this->count['write'];
		$request->cacheDeletes += $this->count['delete'];

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->queries = [];

		$this->count = [
			'read' => 0, 'hit' => 0, 'write' => 0, 'delete' => 0
		];
	}

	// Start listening to cache events
	public function listenToEvents()
	{
		if (class_exists(\Illuminate\Cache\Events\CacheHit::class)) {
			$this->eventDispatcher->listen(\Illuminate\Cache\Events\CacheHit::class, function ($event) {
				$this->registerQuery([ 'type' => 'hit', 'key' => $event->key, 'value' => $event->value ]);
			});
			$this->eventDispatcher->listen(\Illuminate\Cache\Events\CacheMissed::class, function ($event) {
				$this->registerQuery([ 'type' => 'miss', 'key' => $event->key ]);
			});
			$this->eventDispatcher->listen(\Illuminate\Cache\Events\KeyWritten::class, function ($event) {
				$this->registerQuery([
					'type' => 'write', 'key' => $event->key, 'value' => $event->value,
					'expiration' => property_exists($event, 'seconds') ? $event->seconds : $event->minutes * 60
				]);
			});
			$this->eventDispatcher->listen(\Illuminate\Cache\Events\KeyForgotten::class, function ($event) {
				$this->registerQuery([ 'type' => 'delete', 'key' => $event->key ]);
			});
		} else {
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
		}
	}

	// Collect an executed query
	protected function registerQuery(array $query)
	{
		$trace = StackTrace::get()->resolveViewName();

		$record = [
			'type'       => $query['type'],
			'key'        => $query['key'],
			'expiration' => isset($query['expiration']) ? $query['expiration'] : null,
			'time'       => microtime(true),
			'connection' => null,
			'trace'      => (new Serializer)->trace($trace)
		];

		if ($this->collectValues && isset($query['value'])) {
			$record['value'] = (new Serializer)->normalize($query['value']);
		}

		$this->incrementQueryCount($record);

		if ($this->collectQueries && $this->passesFilters([ $record ])) {
			$this->queries[] = $record;
		}
	}

	// Increment query counts for collected query
	protected function incrementQueryCount($query)
	{
		if ($query['type'] == 'write') {
			$this->count['write']++;
		} elseif ($query['type'] == 'delete') {
			$this->count['delete']++;
		} else {
			$this->count['read']++;

			if ($query['type'] == 'hit') {
				$this->count['hit']++;
			}
		}
	}
}
