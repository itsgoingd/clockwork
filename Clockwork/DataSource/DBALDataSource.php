<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Request\Timeline;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Connection;

class DBALDataSource extends DataSource implements SQLLogger
{
	const EVENT_NAME = 'database';

	/**
	 * Internal array where queries are stored
	 */
	protected $queries = [];

	/**
	 * For timing queries:
	 */
	public $start = null;

	/**
	 * Current recorded query
	 */
	public $query = null;

	/**
	 * Doctrine connection
	 */
	protected $connection;

	/**
	 * Clockwork timeline
	 */
	protected $timeline;

	public function __construct(Connection $connection, $options = [])
	{
		$this->connection = $connection;

		$options = array_merge([ 'timeline' => null ], $options);

		$configuration = $this->connection->getConfiguration();
		$currentLogger = $configuration->getSQLLogger();

		if ($currentLogger === null) {
			$configuration->setSQLLogger($this);
		} else {
			$loggerChain = new LoggerChain();
			$loggerChain->addLogger($currentLogger);
			$loggerChain->addLogger($this);

			$configuration->setSQLLogger($loggerChain);
		}

		if ($options['timeline'] instanceof Timeline) {
			$this->setTimeline($options['timeline']);
		}
	}

	/**
	 * From SQLLogger Doctrine Interface
	 */
	public function startQuery($sql, array $params = null, array $types = null)
	{
		$this->start = microtime(true);

		$sql = $this->replaceParams($sql, $params, $types);
		$sql = $this->formatQuery($sql);

		$this->query = [ 'sql' => $sql, 'params' => $params, 'types' => $types ];

		if ($this->timeline !== null) {
			$this->timeline->startEvent(self::EVENT_NAME, $sql);
		}
	}

	protected function formatQuery($sql)
	{
		$keywords = [
			'select', 'insert', 'update', 'delete', 'where', 'from', 'limit', 'is', 'null', 'having', 'group by',
			'order by', 'asc', 'desc'
		];
		$regexp = '/\b' . implode('\b|\b', $keywords) . '\b/i';

		return preg_replace_callback($regexp, function ($match) {
			return strtoupper($match[0]);
		}, $sql);
	}

	protected function replaceParams($sql, $params, $types)
	{
		if (is_array($params)) {
			foreach ($params as $key => $param) {
				$type = isset($types[$key]) ? $types[$key] : null;
				$param = $this->convertParam($param, $type);

				if (is_string($key)) {
					$sql = preg_replace("/:$key/", "$param", $sql);
				} else {
					$sql = preg_replace('/\?/', "$param", $sql, 1);
				}
			}
		}

		return $sql;
	}

	protected function convertParam($param, $type)
	{
		if (is_array($param)) {
			$convertedArray = [];
			foreach ($param as $item) {
				$convertedArray[] = $this->convertParam($item, $type);
			}
			return implode(', ', $convertedArray);
		}

		// Convert param using the same strategy the connection uses
		if ($type && Type::hasType($type)) {
			$type = Type::getType($type);
			$param = $type->convertToDatabaseValue($param, $this->connection->getDatabasePlatform());

			if ($param === null) {
				return 'NULL';
			}

			return '"' . $param . '"';
		}

		// Fall back to converting the param to string ourselves
		if (!is_object($param) || method_exists($param, '__toString')) {
			return '"' . (string)$param . '"';
		}

		return get_class($param);
	}

	/**
	 * From SQLLogger Doctrine Interface
	 */
	public function stopQuery()
	{
		$duration = (microtime(true) - $this->start) * 1000;

		$this->registerQuery($this->query['sql'], $this->query['params'], $duration, $this->connection->getDatabase());

		if ($this->timeline !== null) {
			$this->timeline->endEvent(self::EVENT_NAME);
		}
	}

	/**
	 * Log the query into the internal store
	 */
	public function registerQuery($query, $bindings, $duration, $connection)
	{
		$this->queries[] = [
			'query'      => $query,
			'bindings'   => $bindings,
			'duration'   => $duration,
			'connection' => $connection
		];
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
	 * Returns an array of runnable queries and their durations from the internal array
	 */
	protected function getDatabaseQueries()
	{
		return $this->queries;
	}

	/**
	 * Timeline Getter/Setter
	 */
	public function getTimeline()
	{
		return $this->timeline;
	}

	public function setTimeline(Timeline $timeline)
	{
		return $this->timeline = $timeline;
	}
}
