<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Support\Doctrine\Middleware;
use Clockwork\Support\Doctrine\Legacy\Logger;

use Doctrine\DBAL\{Configuration, Connection};

// Data source for DBAL, provides database queries
class DBALDataSource extends DataSource
{
	// Array of collected queries
	protected $queries = [];

	// DBAL connection name
	protected $connectionName;

	protected $logger;

	// Create a new data source instance, takes a DBAL connection instance as an argument (for Doctrine 2.x)
	public function __construct(?Connection $connection = null)
	{
		$this->connectionName = $connection ? $connection->getDatabase() : null;

		if (! class_exists(\Doctrine\DBAL\Logging\Middleware::class)) {
			$this->setupLegacyDoctrine($connection);
		}
	}

	// Update Doctrine configuration to include the Clockwork logging middleware (for Doctrine 3+)
	public function configure(?Configuration $configuration = null)
	{
		$configuration = $configuration ?? new Configuration;

		return $configuration->setMiddlewares(array_merge(
			$configuration->getMiddlewares(), [ $this->middleware() ]
		));
	}

	// Returns an instance of Clockwork logging middleware associated with this data source (for Doctrine 3+)
	public function middleware()
	{
		return $this->logger = new Middleware(function ($query) {
			$this->registerQuery($query);
		});
	}

	// Setup Clockwork logger for legacy Doctrine 2.x
	protected function setupLegacyDoctrine(Connection $connection)
	{
		$this->logger = new Logger($connection, function ($query) {
			$this->registerQuery($query);
		});
	}

	// Adds executed database queries to the request
	public function resolve(Request $request)
	{
		$request->databaseQueries = array_merge($request->databaseQueries, $this->queries);

		return $request;
	}

	// Reset the data source to an empty state, clearing any collected data
	public function reset()
	{
		$this->queries = [];
		$this->query = null;
	}

	// Collect an executed database query
	protected function registerQuery($query)
	{
		$query['duration'] = (microtime(true) - $query['time']) * 1000;
		$query['connection'] = $query['connection'] ?? $this->connectionName;

		if ($this->passesFilters([ $query ])) {
			$this->queries[] = $query;
		}
	}
}
