<?php namespace Clockwork\Storage;

use Clockwork\Storage\StorageInterface;
use Clockwork\Request\Request;

abstract class Storage implements StorageInterface
{
	// Array of data to be filtered from stored requests
	public $filter = [];

	// Return array of data with applied filter
	protected function applyFilter(array $data)
	{
		$emptyRequest = new Request([]);

		foreach ($this->filter as $key) {
			if (isset($data[$key])) {
				$data[$key] = $emptyRequest->$key;
			}
		}

		return $data;
	}
}
