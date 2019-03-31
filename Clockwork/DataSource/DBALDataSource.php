<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;
use Clockwork\Request\Timeline;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Platforms\AbstractPlatform;
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

		$sql = $this->replaceParams($this->connection->getDatabasePlatform(), $sql, $params, $types);
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
		$query = [
			'query'      => $query,
			'bindings'   => $bindings,
			'duration'   => $duration,
			'connection' => $connection
		];

		if ($this->passesFilters([ $query ])) {
			$this->queries[] = $query;
		}
	}

	/**
	 * Adds ran database queries to the request
	 */
	public function resolve(Request $request)
	{
		$request->databaseQueries = array_merge($request->databaseQueries, $this->queries);

		return $request;
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
