<?php namespace Clockwork\Support\Doctrine\Legacy;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\{LoggerChain, SQLLogger};
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

// Clockwork logger for legacy Doctrine 2.x
class Logger implements SQLLogger
{
	// On-query callback
	protected $onQuery;

	// Current running query
	protected $query = null;

	// DBAL connection
	protected $connection;

	// Create a new logger instance, takes a DBAL connection instance and on-query callback as arguments
	public function __construct(Connection $connection, Callable $onQuery)
	{
		$this->onQuery = $onQuery;

		$this->connection = $connection;

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

	// DBAL SQLLogger event
	public function startQuery($sql, ?array $params = null, ?array $types = null)
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
		($this->onQuery)([
			'query'    => $this->createRunnableQuery($query['query'], $query['params'], $query['types']),
			'bindings' => $query['params'],
			'time'     => $query['time']
		]);
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
	public function replaceParams($platform, $sql, ?array $params = null, ?array $types = null)
	{
		if (is_array($params)) {
			foreach ($params as $key => $param) {
				$type  = $types[$key] ?? null;
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
			$param = htmlspecialchars((string) $param); // Originally used the e() Laravel helper
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
