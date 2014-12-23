<?php namespace Clockwork\DataSource;

use Clockwork\Request\Request;

use Doctrine\ORM\EntityManager;
use Illuminate\Log\Writer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\SQLLogger;


class DoctrineDataSource extends DataSource implements SQLLogger
{
    /**
     * Internal array where queries are stored
     * @var array
     */
    protected $queries = array();

    /**
     * Doctrine event manager
     *
     * @var EventManager
     */
    protected $evm;

    /**
     * Doctrine entity manager
     *
     * @var EntityManager
     */
    protected $enm;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * For timing queries:
     * @var float|null
     */
    public $start = null;

    /**
     * @var null
     */
    public $query = null;

    /**
     * Doctrine connection
     *
     * @var Connection
     */
    protected $connection;

    public function __construct(EntityManager $enm, Writer $logger)
    {
        $this->evm          = $enm->getEventManager();     //Unused, for now
        $this->enm          = $enm;
        $this->logger       = $logger;
        $this->connection   = $enm->getConnection();

        $enm->getConnection()->getConfiguration()->setSQLLogger($this);
    }

    /**
     * From SQLLogger Doctrine Interface
     *
     * @param string $sql
     * @param array $params
     * @param array $types
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        $this->start    = microtime(true);
        $sql            = $this->replaceParams($sql, $params);
        $sql            = $this->formatQuery($sql);

        $this->query    = array( 'sql' => $sql, 'params' => $params, 'types' => $types );
    }

    protected function formatQuery($sql)
    {
        $keywords   = array('select', 'insert', 'update', 'delete', 'where', 'from', 'limit', 'is', 'null', 'having', 'group by', 'order by', 'asc', 'desc');
        $regexp     = '/\b' . implode('\b|\b', $keywords) . '\b/i';

        $sql = preg_replace_callback($regexp, function($match){
            return strtoupper($match[0]);
        }, $sql);

        return $sql;
    }

    protected function replaceParams($sql, $params)
    {
        if (is_array($params)) {

            $pattern        = '/\?/';

            foreach ($params as $param) {

                $param  = $this->convertParam($param);
                $sql    = preg_replace($pattern, "\"$param\"", $sql, 1);
            }
        }

        return $sql;
    }

    protected function convertParam($param)
    {
        if (is_object($param)) {

            if (!method_exists($param, '__toString')) {

                // Carbon Object
                if (is_a($param, 'DateTime'))
                    $param = $param->format('Y-m-d');
                else
                    throw new \Exception('Unstringable Object');
            }
        }

        return $param;
    }

    /**
     * From SQLLogger Doctrine Interface
     */
    public function stopQuery()
    {
        $endTime = microtime(true) - $this->start;

        /**
         * TODO Improve Connection name
         * For now, $this->connection->getDatabase() return the name of the Database
         */

        $this->registerQuery($this->query['sql'], $this->query['params'], $endTime, $this->connection->getDatabase());

        /**
         * This call is helpful to register queries in log file when the application is in debug/local mode
         */
        $this->logger->debug($this->query['sql']. " [Executed in " . $endTime . " secs.] ", array( 'params' => $this->query['params'], 'types' => $this->query['types'] ));
    }

    /**
     * Log the query into the internal store
     * @return array
     */
    public function registerQuery($query, $bindings, $time, $connection)
    {
        $this->queries[] = array(
            'query'      => $query,
            'bindings'   => $bindings,
            'time'       => $time,
            'connection' => $connection
        );
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
        $queries = array();
        foreach ($this->queries as $query)
            $queries[] = array(
                'query'      => $query['query'],
                'duration'   => $query['time'],
                'connection' => $query['connection']
            );

        return $queries;
    }
}
