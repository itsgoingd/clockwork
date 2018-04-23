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

	// Metadata expiration time in minutes
	protected $expiration;

	// List of all fields in the Clockwork requests table
	protected $fields = [
		'id', 'version', 'time', 'method', 'uri', 'headers', 'controller', 'getData', 'postData', 'sessionData',
		'cookies', 'responseTime', 'responseStatus', 'responseDuration', 'databaseQueries', 'databaseDuration',
		'cacheQueries', 'cacheReads', 'cacheHits', 'cacheWrites', 'cacheDeletes', 'cacheTime', 'timelineData', 'log',
		'events', 'routes', 'emailsData', 'viewsData', 'userData'
	];

	// List of Request keys that need to be serialized before they can be stored in database
	protected $needsSerialization = [
		'headers', 'getData', 'postData', 'sessionData', 'cookies', 'databaseQueries', 'cacheQueries', 'timelineData',
		'log', 'events', 'routes', 'emailsData', 'viewsData', 'userData'
	];

	// Return a new storage, takes PDO object or DSN and optionally a table name and database credentials as arguments
	public function __construct($dsn, $table = 'clockwork', $username = null, $password = null, $expiration = null)
	{
		$this->pdo = $dsn instanceof PDO ? $dsn : new PDO($dsn, $username, $password);
		$this->table = $table;
		$this->expiration = $expiration === null ? 60 * 24 * 7 : $expiration;
	}

	// Returns all requests
	public function all()
	{
		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, $this->fields));
		$result = $this->query("SELECT {$fields} FROM {$this->table}");

		return $this->resultsToRequests($result);
	}

	// Return a single request by id
	public function find($id)
	{
		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, $this->fields));
		$result = $this->query("SELECT {$fields} FROM {$this->table} WHERE id = :id", [ 'id' => $id ]);

		$requests = $this->resultsToRequests($result);
		return end($requests);
	}

	// Return the latest request
	public function latest()
	{
		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, $this->fields));
		$result = $this->query("SELECT {$fields} FROM {$this->table} ORDER BY id DESC LIMIT 1");

		$requests = $this->resultsToRequests($result);
		return end($requests);
	}

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null)
	{
		$count = (int) $count;

		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, $this->fields));
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

		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, $this->fields));
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
			$data[$key] = @json_encode($data[$key], defined('JSON_PARTIAL_OUTPUT_ON_ERROR') ? \JSON_PARTIAL_OUTPUT_ON_ERROR : 0);
		}

		$fields = implode(', ', array_map(function ($field) { return $this->quote($field); }, $this->fields));
		$bindings = implode(', ', array_map(function ($field) { return ":{$field}"; }, $this->fields));

		$this->query("INSERT INTO {$this->table} ($fields) VALUES ($bindings)", $data);

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
			$backupTableName = $this->quote("{$this->table}_backup_" . date('Ymd'));
			$this->pdo->exec("ALTER TABLE {$table} RENAME TO {$backupTableName};");
		} catch (\PDOException $e) {
			// this just means the table doesn't yet exist, nothing to do here
		}

		// create the metadata table
		$textType = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql' ? 'MEDIUMTEXT' : 'TEXT';

		$this->pdo->exec(
			"CREATE TABLE {$table} (" .
				$this->quote('id') . ' VARCHAR(100), ' .
				$this->quote('version') . ' INTEGER, ' .
				$this->quote('time') . ' DOUBLE PRECISION NULL, ' .
				$this->quote('method') . ' VARCHAR(10) NULL, ' .
				$this->quote('uri') . " {$textType} NULL, " .
				$this->quote('headers') . " {$textType} NULL, " .
				$this->quote('controller') . ' VARCHAR(250) NULL, ' .
				$this->quote('getData') . " {$textType} NULL, " .
				$this->quote('postData') . " {$textType} NULL, " .
				$this->quote('sessionData') . " {$textType} NULL, " .
				$this->quote('cookies') . " {$textType} NULL, " .
				$this->quote('responseTime') . ' DOUBLE PRECISION NULL, ' .
				$this->quote('responseStatus') . ' INTEGER NULL, ' .
				$this->quote('responseDuration') . ' DOUBLE PRECISION NULL, ' .
				$this->quote('databaseQueries') . " {$textType} NULL, " .
				$this->quote('databaseDuration') . ' DOUBLE PRECISION NULL, ' .
				$this->quote('cacheQueries') . " {$textType} NULL, " .
				$this->quote('cacheReads') . ' INTEGER NULL, ' .
				$this->quote('cacheHits') . ' INTEGER NULL, ' .
				$this->quote('cacheWrites') . ' INTEGER NULL, ' .
				$this->quote('cacheDeletes') . ' INTEGER NULL, ' .
				$this->quote('cacheTime') . ' DOUBLE PRECISION NULL, ' .
				$this->quote('timelineData') . " {$textType} NULL, " .
				$this->quote('log') . " {$textType} NULL, " .
				$this->quote('events') . " {$textType} NULL, " .
				$this->quote('routes') . " {$textType} NULL, " .
				$this->quote('emailsData') . " {$textType} NULL, " .
				$this->quote('viewsData') . " {$textType} NULL, " .
				$this->quote('userData') . " {$textType} NULL" .
			');'
		);
	}

	// Executes an sql query, lazily initiates the clockwork database schema if it's old or doesn't exist yet, returns
	// executed statement or false on error
	protected function query($query, array $bindings = [], $firstTry = true)
	{
		try {
			if ($stmt = $this->pdo->prepare($query)) {
				$stmt->execute($bindings);
				return $stmt;
			}
		} catch (\PDOException $e) {
			$stmt = false;
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
