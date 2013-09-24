<?php
namespace Clockwork\Storage;

use Clockwork\Storage\StorageInterface;
use Clockwork\Request\Request;

/**
 * Base storage class, all storages have to extend this class
 */
abstract class Storage implements StorageInterface
{
	/**
	 * Array of data to be filtered from stored requests
	 */
	public $filter = array();

	/**
	 * Retrieve request specified by id argument, if second argument is specified, array of requests from id to last
	 * will be returned
	 */
	abstract public function retrieve($id = null, $last = null);

	/**
	 * Same as retrieve, but json representations of requests are returned
	 */
	public function retrieveAsJson($id = null, $last = null)
	{
		$requests = $this->retrieve($id, $last);

		if (!$requests)
			return null;

		if (!is_array($requests))
			return $requests->toJson();

		foreach ($requests as &$request)
			$request = $request->toArray();

		return json_encode($requests);
	}

	/**
	 * Store request
	 */
	abstract public function store(Request $request);

	/**
	 * Return array of data with applied filter
	 */
	protected function applyFilter(array $data)
	{
		$emptyRequest = new Request(array());

		foreach ($this->filter as $key) {
			if (isset($data[$key])) {
				$data[$key] = $emptyRequest->$key;
			}
		}

		return $data;
	}
}
