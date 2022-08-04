<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

// Data source for DBAL, provides database queries
class DBALDataSource extends DataSource implements SQLLogger
{
	// Array of collected queries
	protected $queries = [];

	// Current running query
	protected $query = null;

	// DBAL connection
	protected $connection;

	// DBAL connection name
	protected $connectionName;

	// Create a new data source instance, takes a DBAL connection instance as an argument
	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
		$this->connectionName = $this->connection->getDatabase();

		$configuration = $this->connection->getConfiguration();
		$currentLogger = $configuration->getSQLLogger();

		if ($currentLogger === null) {
			$configuration->setSQLLogger($this);
		} else {
			$loggerChain = new LoggerChain;
			$loggerChain->addLogger($currentLogger);
			$loggerChain->addLogger($this);

			$configuration->setSQLLogger($loggerChain);
		}
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

	// DBAL SQLLogger event
	public function startQuery($sql, array $params = null, array $types = null)
	{
		$this->query = [
			'query'  => $sql,
			'params' => $params,
			'types'  => $types,
			'time'   => microtime(true)
		];
	}

	// DBAL SQLLogger event
	public function stopQuery()
	{
		$this->registerQuery($this->query);
		$this->query = null;
	}

	// Collect an executed database query
	protected function registerQuery($query)
	{
		$query = [
			'query'      => $this->createRunnableQuery($query['query'], $query['params'], $query['types']),
			'bindings'   => $query['params'],
			'duration'   => (microtime(true) - $query['time']) * 1000,
			'connection' => $this->connectionName,
			'time'       => $query['time']
		];

		if ($this->passesFilters([ $query ])) {
			$this->queries[] = $query;
		}
	}

	// Takes a query, an array of params and types as arguments, returns runnable query with upper-cased keywords
	protected function createRunnableQuery($query, $params, $types)
	{
		// add params to query
		$query = $this->replaceParams($this->connection->getDatabasePlatform(), $query, $params, $types);

		// highlight keywords
		$keywords = [
			'select', 'insert', 'update', 'delete', 'into', 'values', 'set', 'where', 'from', 'limit', 'is', 'null',
			'having', 'group by', 'order by', 'asc', 'desc'
		];
		$regexp = '/\b' . implode('\b|\b', $keywords) . '\b/i';

		return preg_replace_callback($regexp, function ($match) { return strtoupper($match[0]); }, $query);
	}

	/**
	 * Source at laravel-doctrine/orm LaravelDoctrine\ORM\Loggers\Formatters\ReplaceQueryParams::format().
	 *
	 * @param AbstractPlatform $platform
	 * @param string           $sql
	 * @param array|null       $params
	 * @param array|null       $types
	 *
	 *
	 * @return string
	 */
	public function replaceParams($platform, $sql, array $params = null, array $types = null)
	{
		if (is_array($params)) {
			foreach ($params as $key => $param) {
				$type  = isset($types[$key]) ? $types[$key] : null; // Originally used null coalescing
				$param = $this->convertParam($platform, $param, $type);
				$sql   = preg_replace('/\?/', "$param", $sql, 1);
			}
		}
		return $sql;
	}

	/**
	 * Source at laravel-doctrine/orm LaravelDoctrine\ORM\Loggers\Formatters\ReplaceQueryParams::convertParam().
	 *
	 * @param mixed $param
	 *
	 * @throws \Exception
	 * @return string
	 */
	protected function convertParam($platform, $param, $type = null)
	{
		if (is_object($param)) {
			if (!method_exists($param, '__toString')) {
				if ($param instanceof \DateTimeInterface) {
					$param = $param->format('Y-m-d H:i:s');
				} elseif (Type::hasType($type)) {
					$type  = Type::getType($type);
					$param = $type->convertToDatabaseValue($param, $platform);
				} else {
					throw new \Exception('Given query param is an instance of ' . get_class($param) . ' and could not be converted to a string');
				}
			}
		} elseif (is_array($param)) {
			if ($this->isNestedArray($param)) {
				$param = json_encode($param, JSON_UNESCAPED_UNICODE);
			} else {
				$param = implode(
					', ',
					array_map(
						function ($part) {
							return '"' . (string) $part . '"';
						},
						$param
					)
				);
				return '(' . $param . ')';
			}
		} else {
			$param = htmlspecialchars($param); // Originally used the e() Laravel helper
		}
		return '"' . (string) $param . '"';
	}

	/**
	 * Source at laravel-doctrine/orm LaravelDoctrine\ORM\Loggers\Formatters\ReplaceQueryParams::isNestedArray().
	 *
	 * @param  array $array
	 * @return bool
	 */
	private function isNestedArray(array $array)
	{
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				return true;
			}
		}
		return false;
	}
}
