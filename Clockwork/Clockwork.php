<?php
namespace Clockwork;

use Clockwork\DataSource\DataSourceInterface;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Clockwork\Storage\StorageInterface;

/**
 * Main Clockwork class
 */
class Clockwork
{
	/**
	 * Clockwork version
	 */
	const VERSION = '1.2';

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
	 * Add data from all data soruces to request
	 */
	public function resolveRequest()
	{
		foreach ($this->dataSources as $dataSource)
			$dataSource->resolve($this->request);

		// merge global log and timeline data with data collected from data sources
		$this->request->log = array_merge($this->request->log, $this->log->toArray());
		$this->request->timelineData = array_merge($this->request->timelineData, $this->timeline->finalize());

		// sort log and timeline data by time
		uasort($this->request->log, function($a, $b){ return $a['time'] - $b['time']; });
		uasort($this->request->timelineData, function($a, $b){ return $a['start'] * 1000 - $b['start'] * 1000; });

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
	 * Shortcut for $clockwork->getLog()->log()
	 */
	public function log($message, $level = Log::INFO)
	{
		return $this->getLog()->log($message, $level);
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
	 * Shortcut for $clockwork->getTimeline()->startEvent()
	 */
	public function startEvent($name, $description, $time = null)
	{
		return $this->getTimeline()->startEvent($name, $description, $time);
	}

	/**
	 * Shortcut for $clockwork->getTimeline()->endEvent()
	 */
	public function endEvent($name)
	{
		return $this->getTimeline()->endEvent($name);
	}
}
