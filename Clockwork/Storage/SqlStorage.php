<?php namespace Clockwork\Storage;

use Clockwork\Clockwork;
use Clockwork\Request\Request;

use PDO;

// SQL storage for requests using PDO
class SqlStorage extends Storage
{
	// PDO instance
	protected $pdo;

	// Name of the table with Clockwork requests metadata
	protected $table;

	// Metadata expiration time in minutes
	protected $expiration;

	// Schema for the Clockwork requests table
	protected $fields = [
		'id'                       => 'VARCHAR(100) PRIMARY KEY',
		'version'                  => 'INTEGER',
		'type'                     => 'VARCHAR(100) NULL',
		'time'                     => 'DOUBLE PRECISION NULL',
		'method'                   => 'VARCHAR(10) NULL',
		'url'                      => 'TEXT NULL',
		'uri'                      => 'TEXT NULL',
		'headers'                  => 'TEXT NULL',
		'controller'               => 'VARCHAR(250) NULL',
		'getData'                  => 'TEXT NULL',
		'postData'                 => 'TEXT NULL',
		'requestData'              => 'TEXT NULL',
		'sessionData'              => 'TEXT NULL',
		'authenticatedUser'        => 'TEXT NULL',
		'cookies'                  => 'TEXT NULL',
		'responseTime'             => 'DOUBLE PRECISION NULL',
		'responseStatus'           => 'INTEGER NULL',
		'responseDuration'         => 'DOUBLE PRECISION NULL',
		'memoryUsage'              => 'DOUBLE PRECISION NULL',
		'middleware'               => 'TEXT NULL',
		'databaseQueries'          => 'TEXT NULL',
		'databaseQueriesCount'     => 'INTEGER NULL',
		'databaseSlowQueries'      => 'INTEGER NULL',
		'databaseSelects'          => 'INTEGER NULL',
		'databaseInserts'          => 'INTEGER NULL',
		'databaseUpdates'          => 'INTEGER NULL',
		'databaseDeletes'          => 'INTEGER NULL',
		'databaseOthers'           => 'INTEGER NULL',
		'databaseDuration'         => 'DOUBLE PRECISION NULL',
		'cacheQueries'             => 'TEXT NULL',
		'cacheReads'               => 'INTEGER NULL',
		'cacheHits'                => 'INTEGER NULL',
		'cacheWrites'              => 'INTEGER NULL',
		'cacheDeletes'             => 'INTEGER NULL',
		'cacheTime'                => 'DOUBLE PRECISION NULL',
		'modelsActions'            => 'TEXT NULL',
		'modelsRetrieved'          => 'TEXT NULL',
		'modelsCreated'            => 'TEXT NULL',
		'modelsUpdated'            => 'TEXT NULL',
		'modelsDeleted'            => 'TEXT NULL',
		'redisCommands'            => 'TEXT NULL',
		'queueJobs'                => 'TEXT NULL',
		'timelineData'             => 'TEXT NULL',
		'log'                      => 'TEXT NULL',
		'events'                   => 'TEXT NULL',
		'routes'                   => 'TEXT NULL',
		'notifications'            => 'TEXT NULL',
		'emailsData'               => 'TEXT NULL',
		'viewsData'                => 'TEXT NULL',
		'userData'                 => 'TEXT NULL',
		'subrequests'              => 'TEXT NULL',
		'httpRequests'             => 'TEXT NULL',
		'xdebug'                   => 'TEXT NULL',
		'commandName'              => 'TEXT NULL',
		'commandArguments'         => 'TEXT NULL',
		'commandArgumentsDefaults' => 'TEXT NULL',
		'commandOptions'           => 'TEXT NULL',
		'commandOptionsDefaults'   => 'TEXT NULL',
		'commandExitCode'          => 'INTEGER NULL',
		'commandOutput'            => 'TEXT NULL',
		'jobName'                  => 'TEXT NULL',
		'jobDescription'           => 'TEXT NULL',
		'jobStatus'                => 'TEXT NULL',
		'jobPayload'               => 'TEXT NULL',
		'jobQueue'                 => 'TEXT NULL',
		'jobConnection'            => 'TEXT NULL',
		'jobOptions'               => 'TEXT NULL',
		'testName'                 => 'TEXT NULL',
		'testStatus'               => 'TEXT NULL',
		'testStatusMessage'        => 'TEXT NULL',
		'testAsserts'              => 'TEXT NULL',
		'clientMetrics'            => 'TEXT NULL',
		'webVitals'                => 'TEXT NULL',
		'parent'                   => 'TEXT NULL',
		'updateToken'              => 'VARCHAR(100) NULL'
	];

	// List of Request keys that need to be serialized before they can be stored in database
	protected $needsSerialization = [
		'headers', 'getData', 'postData', 'requestData', 'sessionData', 'authenticatedUser', 'cookies', 'middleware',
		'databaseQueries', 'cacheQueries', 'modelsActions', 'modelsRetrieved', 'modelsCreated', 'modelsUpdated',
		'modelsDeleted', 'redisCommands', 'queueJobs', 'timelineData', 'log', 'events', 'routes', 'notifications',
		'emailsData', 'viewsData', 'userData', 'httpRequests', 'subrequests', 'xdebug', 'commandArguments',
		'commandArgumentsDefaults', 'commandOptions', 'commandOptionsDefaults', 'jobPayload', 'jobOptions', 'testAsserts',
		'parent', 'clientMetrics', 'webVitals'
	];

	// Return a new storage, takes PDO object or DSN and optionally a table name and database credentials as arguments
	public function __construct($dsn, $table = 'clockwork', $username = null, $password = null, $expiration = null)
	{
		$this->pdo = $dsn instanceof PDO ? $dsn : new PDO($dsn, $username, $password);
		$this->table = $table;
		$this->expiration = $expiration === null ? 60 * 24 * 7 : $expiration;
	}

	// Returns all requests
	public function all(?Search $search = null)
	{
		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, array_keys($this->fields)));
		$search = SqlSearch::fromBase($search, $this->pdo);
		$result = $this->query("SELECT {$fields} FROM {$this->table} {$search->query}", $search->bindings);

		return $this->resultsToRequests($result);
	}

	// Return a single request by id
	public function find($id)
	{
		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, array_keys($this->fields)));
		$result = $this->query("SELECT {$fields} FROM {$this->table} WHERE id = :id", [ 'id' => $id ]);

		$requests = $this->resultsToRequests($result);
		return end($requests);
	}

	// Return the latest request
	public function latest(?Search $search = null)
	{
		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, array_keys($this->fields)));
		$search = SqlSearch::fromBase($search, $this->pdo);
		$result = $this->query(
			"SELECT {$fields} FROM {$this->table} {$search->query} ORDER BY id DESC LIMIT 1", $search->bindings
		);

		$requests = $this->resultsToRequests($result);
		return end($requests);
	}

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null, ?Search $search = null)
	{
		$count = (int) $count;

		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, array_keys($this->fields)));
		$search = SqlSearch::fromBase($search, $this->pdo)->addCondition('id < :id', [ 'id' => $id ]);
		$limit = $count ? "LIMIT {$count}" : '';
		$result = $this->query(
			"SELECT {$fields} FROM {$this->table} {$search->query} ORDER BY id DESC {$limit}", $search->bindings
		);

		return array_reverse($this->resultsToRequests($result));
	}

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null, ?Search $search = null)
	{
		$count = (int) $count;

		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, array_keys($this->fields)));
		$search = SqlSearch::fromBase($search, $this->pdo)->addCondition('id > :id', [ 'id' => $id ]);
		$limit = $count ? "LIMIT {$count}" : '';
		$result = $this->query(
			"SELECT {$fields} FROM {$this->table} {$search->query} ORDER BY id ASC {$limit}", $search->bindings
		);

		return $this->resultsToRequests($result);
	}

	// Store the request in the database
	public function store(Request $request)
	{
		$data = $request->toArray();

		foreach ($this->needsSerialization as $key) {
			$data[$key] = @json_encode($data[$key], \JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, array_keys($this->fields)));
		$bindings = implode(', ', array_map(function ($field) { return ":{$field}"; }, array_keys($this->fields)));

		$this->query("INSERT INTO {$this->table} ($fields) VALUES ($bindings)", $data);

		$this->cleanup();
	}

	// Update an existing request in the database
	public function update(Request $request)
	{
		$data = $request->toArray();

		foreach ($this->needsSerialization as $key) {
			$data[$key] = @json_encode($data[$key], \JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		$values = implode(', ', array_map(function ($field) {
			return $this->quote($field) . " = :{$field}";
		}, array_keys($this->fields)));

		$this->query("UPDATE {$this->table} SET {$values} WHERE id = :id", $data);

		$this->cleanup();
	}

	// Cleanup old requests
	public function cleanup()
	{
		if ($this->expiration === false) return;

		$this->query("DELETE FROM {$this->table} WHERE time < :time", [ 'time' => time() - ($this->expiration * 60) ]);
	}

	// Create or update the Clockwork metadata table
	protected function initialize()
	{
		// first we get rid of existing table if it exists by renaming it so we won't lose any data
		try {
			$table = $this->quote($this->table);
			$backupTableName = $this->quote("{$this->table}_backup_" . date('Ymd_His'));
			$this->pdo->exec("ALTER TABLE {$table} RENAME TO {$backupTableName};");

			$indexName = $this->quote("{$this->table}_time_index");
			$this->pdo->exec("DROP INDEX {$indexName};"); // most sql implementations use global index names
			$this->pdo->exec("DROP INDEX {$indexName} ON {$backupTableName};"); // mysql uses table-specific index names
		} catch (\PDOException $e) {
			// this just means the table doesn't yet exist, nothing to do here
		}

		// create the metadata table
		$this->pdo->exec($this->buildSchema($table));

		$indexName = $this->quote("{$this->table}_time_index");
		$this->pdo->exec("CREATE INDEX {$indexName} ON {$table} (". $this->quote('time') .')');
	}

	// Builds the query to create Clockwork database table
	protected function buildSchema($table)
	{
		$textType = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql' ? 'MEDIUMTEXT' : 'TEXT';

		$columns = implode(', ', array_map(function ($field, $type) use ($textType) {
			return $this->quote($field) . ' ' . str_replace('TEXT', $textType, $type);
		}, array_keys($this->fields), array_values($this->fields)));

		return "CREATE TABLE {$table} ({$columns});";
	}

	// Executes an sql query, lazily initiates the clockwork database schema if it's old or doesn't exist yet, returns
	// executed statement or false on error
	protected function query($query, array $bindings = [], $firstTry = true)
	{
		try {
			if ($stmt = $this->pdo->prepare($query)) {
				if ($stmt->execute($bindings)) return $stmt;
				throw new \PDOException;
			}
		} catch (\PDOException $e) {
			$stmt = strpos($e->getMessage(), 'Integrity constraint violation') !== false;
		}

		// the query failed to execute, assume it's caused by missing or old schema, try to reinitialize database
		if (! $stmt && $firstTry) {
			$this->initialize();
			return $this->query($query, $bindings, false);
		}
	}

	// Quotes SQL identifier name properly for the current database
	protected function quote($identifier)
	{
		return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql' ? "`{$identifier}`" : "\"{$identifier}\"";
	}

	// Returns array of Requests instances from the executed PDO statement
	protected function resultsToRequests($stmt)
	{
		return array_map(function ($data) {
			return $this->dataToRequest($data);
		}, $stmt->fetchAll(PDO::FETCH_ASSOC));
	}

	// Returns a Request instance from a single database record
	protected function dataToRequest($data)
	{
		foreach ($this->needsSerialization as $key) {
			$data[$key] = json_decode($data[$key], true);
		}

		return new Request($data);
	}
}
