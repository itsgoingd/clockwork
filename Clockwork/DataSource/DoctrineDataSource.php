<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\EntityManager;

class DoctrineDataSource extends DataSource implements SQLLogger
{
	/**
	 * Internal array where queries are stored
	 */
	protected $queries = [];

	/**
	 * Doctrine entity manager
	 */
	protected $enm;

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

	public function __construct(EntityManager $enm)
	{
		$this->enm = $enm;
		$this->connection = $enm->getConnection();

		$enm->getConnection()->getConfiguration()->setSQLLogger($this);
	}

	/**
	 * From SQLLogger Doctrine Interface
	 */
	public function startQuery($sql, array $params = null, array $types = null)
	{
		$this->start = microtime(true);

		$sql = $this->replaceParams($sql, $params);
		$sql = $this->formatQuery($sql);

		$this->query = [ 'sql' => $sql, 'params' => $params, 'types' => $types ];
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

	protected function replaceParams($sql, $params)
	{
		if (is_array($params)) {
			foreach ($params as $param) {
				$param = $this->convertParam($param);
				$sql   = preg_replace('/\?/', "$param", $sql, 1);
			}
		}

		return $sql;
	}

	protected function convertParam($param)
	{
		if (is_object($param)) {
			if (! method_exists($param, '__toString')) {
				if ($param instanceof DateTime || $param instanceof DateTimeImmutable) {
					$param = $param->format('Y-m-d H:i:s');
				} else {
					throw new Exception('Given query param is an instance of ' . get_class($param) . ' and could not be converted to a string');
				}
			}
		} elseif (is_array($param)) {
			if (count($param) !== count($param, COUNT_RECURSIVE)) {
				$param = json_encode($param, JSON_UNESCAPED_UNICODE);
			} else {
				$param = implode(', ', array_map(function ($part) {
					return '"' . (string) $part . '"';
				}, $param));

				return '(' . $param . ')';
			}
		}

		return '"' . (string) $param . '"';
	}

	/**
	 * From SQLLogger Doctrine Interface
	 */
	public function stopQuery()
	{
		$duration = (microtime(true) - $this->start) * 1000;

		$this->registerQuery($this->query['sql'], $this->query['params'], $duration, $this->connection->getDatabase());
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
}
