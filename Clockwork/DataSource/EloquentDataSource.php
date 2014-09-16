<?php
namespace Clockwork\DataSource;

use Clockwork\Request\Request;

use Illuminate\Database\DatabaseManager;
use Illuminate\Events\Dispatcher;

/**
 * Data source for Eloquent (Laravel 4 ORM), provides database queries
 */
class EloquentDataSource extends DataSource
{
	/**
	 * Database manager
	 */
	protected $dbmanager;

	/**
	 * Internal array where queries are stored
	 * @var array
	 */
    protected $queries = array();

	/**
	 * Create a new data source instance, takes a database manager and an event dispatcher as arguments
	 */
	public function __construct(DatabaseManager $dbmanager, Dispatcher $dispatcher)
	{
		$this->dbmanager = $dbmanager;
        $this->dispatcher = $dispatcher;
	}

	/**
	 * Start listening to eloquent queries
	 */
    public function listenToEvents()
    {
        $this->dispatcher->listen('illuminate.query', [$this, 'registerQuery']);
    }

    /**
     * Log the query into the internal store
     * @return array
     */
    public function registerQuery()
    {
        $args = func_get_args();

        $this->queries[] = array(
            'query' => $args[0],
            'bindings' => $args[1],
            'time' => $args[2],
            'connection' => $args[3]
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
		$bindings = $this->dbmanager->connection($connection)->prepareBindings($bindings);

		foreach ($bindings as $binding) {
			$binding = $this->dbmanager->connection($connection)->getPdo()->quote($binding);

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
				'query'    => $this->createRunnableQuery($query['query'], $query['bindings'], $query['connection']),
				'duration' => $query['time'],
			);

		return $queries;
	}
}
