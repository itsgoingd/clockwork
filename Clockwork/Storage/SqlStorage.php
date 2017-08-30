<?php namespace Clockwork\Storage;

use Clockwork\Clockwork;
use Clockwork\Request\Request;

use Exception;
use PDO;

/**
 * SQL storage for requests
 */
class SqlStorage extends Storage
{
	/**
	 * PDO instance
	 */
	protected $pdo;

	/**
	 * Name of the table with Clockwork requests metadata
	 */
	protected $table;

	/**
	 * List of Request keys that need to be serialized before they can be stored in database
	 */
	protected $needs_serialization = array(
		'headers', 'getData', 'postData', 'sessionData', 'cookies', 'databaseQueries', 'timelineData', 'log', 'routes',
		'emailsData', 'viewsData', 'userData'
	);

	/**
	 * Return a new storage, takes PDO object or DSN and optionally a table name and database credentials as arguments
	 */
	public function __construct($dsn, $table = 'clockwork', $username = null, $password = null)
	{
		if ($dsn instanceof PDO) {
			$this->pdo = $dsn;
		} else {
			$this->pdo = new PDO($dsn, $username, $password);
		}

		$this->table = $table;
	}

	/**
	 * Retrieve a request specified by id argument, if second argument is specified, array of requests from id to last
	 * will be returned
	 */
	public function retrieve($id = null, $last = null)
	{
		if (!$id) {
			$stmt = $this->pdo->prepare(
				'SELECT id, version, time, method, uri, headers, controller, getData, postData, sessionData, cookies, responseTime, responseStatus, responseDuration, databaseQueries, databaseDuration, timelineData, log, routes, emailsData, viewsData, userData ' .
				"FROM {$this->table} "
			);

			$stmt->execute();
			$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

			$requests = array();

			foreach ($data as $item) {
				$requests[] = $this->createRequestFromData($item);
			}

			return $requests;
		}

		$stmt = $this->pdo->prepare(
			'SELECT id, version, time, method, uri, headers, controller, getData, postData, sessionData, cookies, responseTime, responseStatus, responseDuration, databaseQueries, databaseDuration, timelineData, log, routes, emailsData, viewsData, userData ' .
			"FROM {$this->table} " .
			'WHERE id = :id'
		);

		$stmt->execute(array('id' => $id));
		$data = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$data) {
			return null;
		}

		if (!$last) {
			return $this->createRequestFromData($data);
		}

		$stmt = $this->pdo->prepare(
			'SELECT (id, version, time, method, uri, headers, controller, getData, postData, sessionData, cookies, responseTime, responseStatus, responseDuration, databaseQueries, databaseDuration, timelineData, log, routes, emailsData, viewsData, userData) ' .
			"FROM {$this->table} " .
			"WHERE id = :id"
		);

		$stmt->execute(array('id' => $last));
		$last_data = $stmt->fetch(PDO::FETCH_ASSOC);

		$stmt = $this->pdo->prepare(
			'SELECT (id, version, time, method, uri, headers, controller, getData, postData, sessionData, cookies, responseTime, responseStatus, responseDuration, databaseQueries, databaseDuration, timelineData, log, routes, emailsData, viewsData, userData) ' .
			"FROM {$this->table} " .
			"WHERE time >= :from AND time <= :to"
		);

		$stmt->execute(array('from' => $data['time'], 'to' => $last_data['time']));
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$requests = array();

		foreach ($data as $item) {
			$requests[] = $this->createRequestFromData($item);
		}

		return $requests;
	}

	/**
	 * Store the request in the database
	 */
	public function store(Request $request)
	{
		$data = $this->applyFilter($request->toArray());

		foreach ($this->needs_serialization as $key) {
			$data[$key] = @json_encode($data[$key]);
		}

		$data['version'] = Clockwork::VERSION;

		$stmt = $this->pdo->prepare(
			"INSERT INTO {$this->table} " .
			'(id, version, time, method, uri, headers, controller, getData, postData, sessionData, cookies, responseTime, responseStatus, responseDuration, databaseQueries, databaseDuration, timelineData, log, routes, emailsData, viewsData, userData) ' .
			'VALUES ' .
			'(:id, :version, :time, :method, :uri, :headers, :controller, :getData, :postData, :sessionData, :cookies, :responseTime, :responseStatus, :responseDuration, :databaseQueries, :databaseDuration, :timelineData, :log, :routes, :emailsData, :viewsData, :userData)'
		);

		$stmt->execute($data);
	}

	/**
	 * Create the Clockwork metadata table if it doesn't exist
	 */
	public function initialize()
	{
		try {
			$initialized = $this->pdo->query("SELECT 1 FROM {$this->table} LIMIT 1");
		} catch (\Exception $e) {
			$initialized = false;
		}

		if ($initialized !== false) {
			return;
		}

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
				'timelineData MEDIUMTEXT NULL, ' .
				'log MEDIUMTEXT NULL, ' .
				'routes MEDIUMTEXT NULL, ' .
				'emailsData MEDIUMTEXT NULL, ' .
				'viewsData MEDIUMTEXT NULL, ' .
				'userData MEDIUMTEXT NULL' .
			');'
		);
	}

	protected function createRequestFromData($data)
	{
		foreach ($this->needs_serialization as $key) {
			$data[$key] = json_decode($data[$key], true);
		}

		return new Request($data);
	}
}
