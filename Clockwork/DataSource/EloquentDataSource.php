<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;

use Illuminate\Database\DatabaseManager;
use Illuminate\Events\Dispatcher as EventDispatcher;

/**
 * Data source for Eloquent (Laravel 4 ORM), provides database queries
 */
class EloquentDataSource extends DataSource
{
	/**
	 * Database manager
	 */
	protected $databaseManager;

	/**
	 * Internal array where queries are stored
	 * @var array
	 */
	protected $queries = [];

	/**
	 * Create a new data source instance, takes a database manager and an event dispatcher as arguments
	 */
	public function __construct(DatabaseManager $databaseManager, EventDispatcher $eventDispatcher)
	{
		$this->databaseManager = $databaseManager;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * Start listening to eloquent queries
	 */
	public function listenToEvents()
	{
		if (class_exists('Illuminate\Database\Events\QueryExecuted')) {
			// Laravel 5.2
			$this->eventDispatcher->listen('Illuminate\Database\Events\QueryExecuted', [ $this, 'registerQuery' ]);
		} else {
			// Laravel 5.1
			$this->eventDispatcher->listen('illuminate.query', [ $this, 'registerLegacyQuery' ]);
		}
	}

	/**
	 * Log the query into the internal store
	 */
	public function registerQuery($event)
	{
		$this->queries[] = [
			'query'      => $event->sql,
			'bindings'   => $event->bindings,
			'time'       => $event->time,
			'connection' => $event->connectionName
		];
	}

	/**
	 * Log a legacy (pre Laravel 5.2) query into the internal store
	 */
	public function registerLegacyQuery($sql, $bindings, $time, $connection)
	{
		return $this->registerQuery((object) array(
			'sql'            => $sql,
			'bindings'       => $bindings,
			'time'           => $time,
			'connectionName' => $connection
		));
	}

	/**
	 * Adds ran database queries to the request
	 */
	public function resolve(Request $request)
	{
		$request->databaseQueries = array_merge($request->databaseQueries, $this->getDatabaseQueries());

		return $request;
	}

	/**
	 * Takes a query, an array of bindings and the connection as arguments, returns runnable query with upper-cased keywords
	 */
	protected function createRunnableQuery($query, $bindings, $connection)
	{
		# add bindings to query
		$bindings = $this->databaseManager->connection($connection)->prepareBindings($bindings);

		foreach ($bindings as $binding) {
			$binding = $this->databaseManager->connection($connection)->getPdo()->quote($binding);

			$query = preg_replace('/\?/', $binding, $query, 1);
		}

		# highlight keywords
		$keywords = [ 'select', 'insert', 'update', 'delete', 'where', 'from', 'limit', 'is', 'null', 'having', 'group by', 'order by', 'asc', 'desc' ];
		$regexp = '/\b' . implode('\b|\b', $keywords) . '\b/i';

		$query = preg_replace_callback($regexp, function($match)
		{
			return strtoupper($match[0]);
		}, $query);

		return $query;
	}

	/**
	 * Returns an array of runnable queries and their durations from the internal array
	 */
	protected function getDatabaseQueries()
	{
		$queries = [];

		foreach ($this->queries as $query)
			$queries[] = [
				'query'      => $this->createRunnableQuery($query['query'], $query['bindings'], $query['connection']),
				'duration'   => $query['time'],
				'connection' => $query['connection']
			];

		return $queries;
	}
}
