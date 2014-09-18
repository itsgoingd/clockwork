<?php
namespace Clockwork\DataSource;

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
	protected $queries = array();

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
		$this->eventDispatcher->listen('illuminate.query', array($this, 'registerQuery'));
	}

	/**
	 * Log the query into the internal store
	 * @return array
	 */
	public function registerQuery($query, $bindings, $time, $connection)
	{
		$this->queries[] = array(
			'query'      => $query,
			'bindings'   => $bindings,
			'time'       => $time,
			'connection' => $connection
		);
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
		$keywords = array('select', 'insert', 'update', 'delete', 'where', 'from', 'limit', 'is', 'null', 'having', 'group by', 'order by', 'asc', 'desc');
		$regexp = '/\b' . implode('\b|\b', $keywords) . '\b/i';

		$query = preg_replace_callback($regexp, function($match){
			return strtoupper($match[0]);
		}, $query);

		return $query;
	}

	/**
	 * Returns an array of runnable queries and their durations from the internal array
	 */
	protected function getDatabaseQueries()
	{
		$queries = array();

		foreach ($this->queries as $query)
			$queries[] = array(
				'query'      => $this->createRunnableQuery($query['query'], $query['bindings'], $query['connection']),
				'duration'   => $query['time'],
				'connection' => $query['connection']
			);

		return $queries;
	}
}