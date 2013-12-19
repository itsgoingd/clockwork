<?php
namespace Clockwork\DataSource;

use Clockwork\Request\Request;

use Illuminate\Database\Connection;

/**
 * Data source for Eloquent (Laravel 4 ORM), provides database queries
 */
class EloquentDataSource extends DataSource
{
	/**
	 * Database connection for which the queries are retrieved
	 */
	protected $connection;

	/**
	 * Create a new data source instance, takes a database connection as an argument
	 */
	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
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
	 * Takes a query and array of bindings as arguments, returns runnable query with upper-cased keywords
	 */
	protected function createRunnableQuery($query, $bindings)
	{
		# add bindings to query
		$bindings = $this->connection->prepareBindings($bindings);

		foreach ($bindings as $binding) {
			$binding = $this->connection->getPdo()->quote($binding);

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
	 * Returns an array of runnable queries and their durations from a database connection
	 */
	protected function getDatabaseQueries()
	{
		$queries = array();

		foreach ($this->connection->getQueryLog() as $query)
			$queries[] = array(
				'query'    => $this->createRunnableQuery($query['query'], $query['bindings']),
				'duration' => $query['time'],
			);

		return $queries;
	}
}
