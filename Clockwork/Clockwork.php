<?php namespace Clockwork;

use Clockwork\Authentication\AuthenticatorInterface;
use Clockwork\Authentication\NullAuthenticator;
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
	const VERSION = '4.0.14';

	/**
	 * Array of data sources, these objects provide data to be stored in a request object
	 */
	protected $dataSources = [];

	/**
	 * Request object, data structure which stores data about current application request
	 */
	protected $request;

	/**
	 * Storage object, provides implementation for storing and retrieving request objects
	 */
	protected $storage;

	// Authenticator implementation, authenticates requests for clockwork metadata
	protected $authenticator;

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
		$this->request = new Request;
		$this->log = new Log;
		$this->timeline = new Timeline;
		$this->authenticator = new NullAuthenticator;
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
		foreach ($this->dataSources as $dataSource) {
			$dataSource->resolve($this->request);
		}

		// merge global log and timeline data with data collected from data sources
		$this->request->log = array_merge($this->request->log, $this->log->toArray());
		$this->request->timelineData = array_merge($this->request->timelineData, $this->timeline->finalize($this->request->time));

		// sort log and timeline data by time
		uasort($this->request->log, function($a, $b) {
			if ($a['time'] == $b['time']) return 0;
			return $a['time'] < $b['time'] ? -1 : 1;
		});
		uasort($this->request->timelineData, function($a, $b) {
			if ($a['start'] == $b['start']) return 0;
			return $a['start'] < $b['start'] ? -1 : 1;
		});

		return $this;
	}

	// Extends the request with additional data form all data sources when being shown in the Clockwork app
	public function extendRequest(Request $request = null)
	{
		foreach ($this->dataSources as $dataSource) {
			$dataSource->extend($request ?: $this->request);
		}

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
	 * Return the authenticator object
	 */
	public function getAuthenticator()
	{
		return $this->authenticator;
	}

	/**
	 * Set a custom authenticator object
	 */
	public function setAuthenticator(AuthenticatorInterface $authenticator)
	{
		$this->authenticator = $authenticator;

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

		return $this;
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

		return $this;
	}

	/**
	 * Shortcut methods for the current log instance
	 */

	public function log($level = LogLevel::INFO, $message, array $context = [])
	{
		return $this->getLog()->log($level, $message, $context);
	}

	public function emergency($message, array $context = [])
	{
		return $this->getLog()->log(LogLevel::EMERGENCY, $message, $context);
	}

	public function alert($message, array $context = [])
	{
		return $this->getLog()->log(LogLevel::ALERT, $message, $context);
	}

	public function critical($message, array $context = [])
	{
		return $this->getLog()->log(LogLevel::CRITICAL, $message, $context);
	}

	public function error($message, array $context = [])
	{
		return $this->getLog()->log(LogLevel::ERROR, $message, $context);
	}

	public function warning($message, array $context = [])
	{
		return $this->getLog()->log(LogLevel::WARNING, $message, $context);
	}

	public function notice($message, array $context = [])
	{
		return $this->getLog()->log(LogLevel::NOTICE, $message, $context);
	}

	public function info($message, array $context = [])
	{
		return $this->getLog()->log(LogLevel::INFO, $message, $context);
	}

	public function debug($message, array $context = [])
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

	// Shortcut methods for the Request object

	// Add database query, takes query, bindings, duration and additional data - connection (connection name), file
	// (caller file name), line (caller line number), trace (serialized trace), model (associated ORM model)
	public function addDatabaseQuery($query, $bindings = [], $duration = null, $data = [])
	{
		return $this->getRequest()->addDatabaseQuery($query, $bindings, $duration, $data);
	}

	// Add cache query, takes type, key, value and additional data - connection (connection name), file
	// (caller file name), line (caller line number), trace (serialized trace), expiration
	public function addCacheQuery($type, $key, $value = null, $duration = null, $data = [])
	{
		return $this->getRequest()->addCacheQuery($type, $key, $value, $duration, $data);
	}

	// Add event, takes event name, data, time and additional data - listeners, file (caller file name), line (caller
	// line number), trace (serialized trace)
	public function addEvent($event, $eventData = null, $time = null, $data = [])
	{
		return $this->getRequest()->addEvent($event, $eventData, $time, $data);
	}

	// Add route, takes method, uri, action and additional data - name, middleware, before (before filters), after
	// (after filters)
	public function addRoute($method, $uri, $action, $data = [])
	{
		return $this->getRequest()->addRoute($method, $uri, $action, $data);
	}

	// Add route, takes method, uri, action and additional data - name, middleware, before (before filters), after
	// (after filters)
	public function addEmail($subject, $to, $from = null, $headers = [])
	{
		return $this->getRequest()->addEmail($subject, $to, $from, $headers);
	}

	// Add view, takes view name and data
	public function addView($name, $data = [])
	{
		return $this->getRequest()->addView($name, $data);
	}

	// Add executed subrequest, takes the requested url, suvrequest Clockwork ID and additional data - path if non-default,
	// start and end time or duration in seconds to add the subrequest to the timeline
	public function addSubrequest($url, $id, $data = [])
	{
		if (isset($data['duration'])) {
			$data['end'] = microtime(true);
			$data['start'] = $data['end'] - $data['duration'];
		}

		if (isset($data['start'])) {
			$this->timeline->addEvent(
				"subrequest-{$id}", "Subrequest - {$url}", $data['start'], isset($data['end']) ? $data['end'] : null
			);
		}

		return $this->getRequest()->addSubrequest($url, $id, $data);
	}

	// DEPRECATED Use addSubrequest method
	public function subrequest($url, $id, $path = null)
	{
		return $this->getRequest()->addSubrequest($url, $id, $path);
	}

	// Add custom user data (presented as additional tabs in the official app)
	public function userData($key = null)
	{
		return $this->getRequest()->userData($key);
	}
}
