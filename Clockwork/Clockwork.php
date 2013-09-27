<?php
namespace Clockwork;

use Clockwork\DataSource\DataSourceInterface;
use Clockwork\Request\Request;
use Clockwork\Storage\StorageInterface;

/**
 * Main Clockwork class
 */
class Clockwork
{
	/**
	 * Clockwork version
	 */
	const VERSION = '1.1';

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
	 * Create a new Clockwork instance with default request object
	 */
	public function __construct()
	{
		$this->request = new Request();
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
}
