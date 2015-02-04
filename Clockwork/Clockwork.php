<?php
namespace Clockwork;

use Clockwork\DataSource\DataSourceInterface;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Clockwork\Storage\StorageInterface;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

/**
 * Main Clockwork class
 */
class Clockwork implements LoggerInterface
{
	/**
	 * Clockwork version
	 */
	const VERSION = '1.7';

	/**
	 * Array of data sources, these objects provide data to be stored in a request object
	 */
	protected $dataSources = array();

	/**
	 * Request object, data structure which stores data about current application request
	 */
	protected $request;

	/**
	 * Storage object, provides implementation for storing and retrieving request objects
	 */
	protected $storage;

	/**
	 * Request\Log instance, data structure which stores data for the log view
	 */
	protected $log;

	/**
	 * Request\Timeline instance, data structure which stores data for the timeline view
	 */
	protected $timeline;

	/**
	 * Create a new Clockwork instance with default request object
	 */
	public function __construct()
	{
		$this->request = new Request();
		$this->log = new Log();
		$this->timeline = new Timeline();
	}

	/**
	 * Add a new data source
	 */
	public function addDataSource(DataSourceInterface $dataSource)
	{
		$this->dataSources[] = $dataSource;

		return $this;
	}

	/**
	 * Return array of all added data sources
	 */
	public function getDataSources()
	{
		return $this->dataSources;
	}

	/**
	 * Return the request object
	 */
	public function getRequest()
	{
		return $this->request;
	}

	/**
	 * Set a custom request object
	 */
	public function setRequest(Request $request)
	{
		$this->request = $request;

		return $this;
	}

	/**
	 * Add data from all data sources to request
	 */
	public function resolveRequest()
	{
		foreach ($this->dataSources as $dataSource)
			$dataSource->resolve($this->request);

		// merge global log and timeline data with data collected from data sources
		$this->request->log = array_merge($this->request->log, $this->log->toArray());
		$this->request->timelineData = array_merge($this->request->timelineData, $this->timeline->finalize());

		// sort log and timeline data by time
		uasort($this->request->log, function($a, $b)
		{
			if ($a['time'] == $b['time']) return 0;
			return $a['time'] < $b['time'] ? -1 : 1;
		});
		uasort($this->request->timelineData, function($a, $b)
		{
			if ($a['start'] == $b['start']) return 0;
			return $a['start'] < $b['start'] ? -1 : 1;
		});

		return $this;
	}

	/**
	 * Store request via storage object
	 */
	public function storeRequest()
	{
		return $this->storage->store($this->request);
	}

	/**
	 * Return the storage object
	 */
	public function getStorage()
	{
		return $this->storage;
	}

	/**
	 * Set a custom storage object
	 */
	public function setStorage(StorageInterface $storage)
	{
		$this->storage = $storage;

		return $this;
	}

	/**
	 * Return the log instance
	 */
	public function getLog()
	{
		return $this->log;
	}

	/**
	 * Set a custom log instance
	 */
	public function setLog(Log $log)
	{
		$this->log = $log;
	}

	/**
	 * Return the timeline instance
	 */
	public function getTimeline()
	{
		return $this->timeline;
	}

	/**
	 * Set a custom timeline instance
	 */
	public function setTimeline(Timeline $timeline)
	{
		$this->timeline = $timeline;
	}

	/**
	 * Shortcut methods for the current log instance
	 */

	public function log($level = LogLevel::INFO, $message, array $context = array())
	{
		return $this->getLog()->log($level, $message, $context);
	}

	public function emergency($message, array $context = array())
	{
		return $this->getLog()->log(LogLevel::EMERGENCY, $message, $context);
	}

	public function alert($message, array $context = array())
	{
		return $this->getLog()->log(LogLevel::ALERT, $message, $context);
	}

	public function critical($message, array $context = array())
	{
		return $this->getLog()->log(LogLevel::CRITICAL, $message, $context);
	}

	public function error($message, array $context = array())
	{
		return $this->getLog()->log(LogLevel::ERROR, $message, $context);
	}

	public function warning($message, array $context = array())
	{
		return $this->getLog()->log(LogLevel::WARNING, $message, $context);
	}

	public function notice($message, array $context = array())
	{
		return $this->getLog()->log(LogLevel::NOTICE, $message, $context);
	}

	public function info($message, array $context = array())
	{
		return $this->getLog()->log(LogLevel::INFO, $message, $context);
	}

	public function debug($message, array $context = array())
	{
		return $this->getLog()->log(LogLevel::DEBUG, $message, $context);
	}

	/**
	 * Shortcut methods for the current timeline instance
	 */

	public function startEvent($name, $description, $time = null)
	{
		return $this->getTimeline()->startEvent($name, $description, $time);
	}

	public function endEvent($name)
	{
		return $this->getTimeline()->endEvent($name);
	}
}
