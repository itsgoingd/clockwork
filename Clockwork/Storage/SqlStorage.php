<?php namespace Clockwork\Storage;

use Clockwork\Clockwork;
use Clockwork\Request\Request;

use PDO;

/**
 * SQL storage for requests using PDO
 */
class SqlStorage extends Storage
{
	// PDO instance
	protected $pdo;

	// Name of the table with Clockwork requests metadata
	protected $table;

	// List of all fields in the Clockwork requests table
	protected $fields = [
		'id', 'version', 'time', 'method', 'uri', 'headers', 'controller', 'getData',
		'postData', 'sessionData', 'cookies', 'responseTime', 'responseStatus', 'responseDuration',
		'databaseQueries', 'databaseDuration', 'cacheQueries', 'cacheReads', 'cacheHits', 'cacheWrites',
		'cacheDeletes', 'cacheTime', 'timelineData', 'log', 'routes', 'emailsData', 'viewsData', 'userData'
	];

	// List of Request keys that need to be serialized before they can be stored in database
	protected $needsSerialization = [
		'headers', 'getData', 'postData', 'sessionData', 'cookies', 'databaseQueries', 'cacheQueries', 'timelineData',
		'log', 'routes', 'emailsData', 'viewsData', 'userData'
	];

	// Return a new storage, takes PDO object or DSN and optionally a table name and database credentials as arguments
	public function __construct($dsn, $table = 'clockwork', $username = null, $password = null)
	{
		if ($dsn instanceof PDO) {
			$this->pdo = $dsn;
		} else {
			$this->pdo = new PDO($dsn, $username, $password);
		}

		$this->table = $table;
	}

	// Returns all requests
	public function all()
	{
		$fields = implode(', ', $this->fields);
		$result = $this->query("SELECT {$fields} FROM {$this->table}");

		return $this->resultsToRequests($result);
	}

	// Return a single request by id
	public function find($id)
	{
		$fields = implode(', ', $this->fields);
		$result = $this->query("SELECT {$fields} FROM {$this->table} WHERE id = :id", [ 'id' => $id ]);

		$requests = $this->resultsToRequests($result);
		return end($requests);
	}

	// Return the latest request
	public function latest()
	{
		$fields = implode(', ', $this->fields);
		$result = $this->query("SELECT {$fields} FROM {$this->table} ORDER BY id DESC LIMIT 1");

		$requests = $this->resultsToRequests($result);
		return end($requests);
	}

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null)
	{
		$count = (int) $count;

		$fields = implode(', ', $this->fields);
		$result = $this->query(
			"SELECT {$fields} FROM {$this->table} WHERE id < :id ORDER BY id DESC " . ($count ? "LIMIT {$count}" : ''),
			[ 'id' => $id ]
		);

		return array_reverse($this->resultsToRequests($result));
	}

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null)
	{
		$count = (int) $count;

		$fields = implode(', ', $this->fields);
		$result = $this->query(
			"SELECT {$fields} FROM {$this->table} WHERE id > :id ORDER BY id ASC " . ($count ? "LIMIT {$count}" : ''),
			[ 'id' => $id ]
		);

		return $this->resultsToRequests($result);
	}

	// Store the request in the database
	public function store(Request $request)
	{
		$data = $this->applyFilter($request->toArray());

		foreach ($this->needsSerialization as $key) {
			$data[$key] = @json_encode($data[$key]);
		}

		$data['version'] = Clockwork::VERSION;

		$fields = implode(', ', $this->fields);
		$bindings = implode(', ', array_map(function ($field) { return ":{$field}"; }, $this->fields));

		$this->query("INSERT INTO {$this->table} ($fields) VALUES ($bindings)", $data);
	}

	// Create or update the Clockwork metadata table
	protected function initialize()
	{
		// first we get rid of existing table if it exists by renaming it so we won't lose any data
		try {
			$backupTableName = "{$this->table}_backup_" . date('Ymd');
			$this->pdo->exec("ALTER TABLE {$this->table} RENAME TO {$backupTableName};");
		} catch (\PDOException $e) {
			// this just means the table doesn't yet exist, nothing to do here
		}

		// create the metadata table
		$this->pdo->exec(
			"CREATE TABLE {$this->table} (" .
				'id VARCHAR(100), ' .
				'version INTEGER, ' .
				'time DOUBLE NULL, ' .
				'method VARCHAR(10) NULL, ' .
				'uri VARCHAR(250) NULL, ' .
				'headers MEDIUMTEXT NULL, ' .
				'controller VARCHAR(250) NULL, ' .
				'getData MEDIUMTEXT NULL, ' .
				'postData MEDIUMTEXT NULL, ' .
				'sessionData MEDIUMTEXT NULL, ' .
				'cookies MEDIUMTEXT NULL, ' .
				'responseTime DOUBLE NULL, ' .
				'responseStatus INTEGER NULL, ' .
				'responseDuration DOUBLE NULL, ' .
				'databaseQueries MEDIUMTEXT NULL, ' .
				'databaseDuration DOUBLE NULL, ' .
				'cacheQueries MEDIUMTEXT NULL, ' .
				'cacheReads INTEGER NULL, ' .
				'cacheHits INTEGER NULL, ' .
				'cacheWrites INTEGER NULL, ' .
				'cacheDeletes INTEGER NULL, ' .
				'cacheTime DOUBLE NULL, ' .
				'timelineData MEDIUMTEXT NULL, ' .
				'log MEDIUMTEXT NULL, ' .
				'routes MEDIUMTEXT NULL, ' .
				'emailsData MEDIUMTEXT NULL, ' .
				'viewsData MEDIUMTEXT NULL, ' .
				'userData MEDIUMTEXT NULL' .
			');'
		);
	}

	// Executes an sql query, lazily initiates the clockwork database schema if it's old or doesn't exist yet, returns
	// executed statement or false on error
	protected function query($query, array $bindings = array(), $firstTry = true)
	{
		try {
			$stmt = $this->pdo->prepare($query);
		} catch (\PDOException $e) {
			$stmt = false;
		}

		// the query failed to execute, assume it's caused by missing or old schema, try to reinitialize database
		if (! $stmt && $firstTry) {
			$this->initialize();
			$this->query($query, $bindings, false);
		}

		if ($stmt) {
			$stmt->execute($bindings);
		}

		return $stmt;
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
