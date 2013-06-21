<?php
namespace Clockwork\Storage;

use Clockwork\Request\Request;

/**
 * Base storage class, all storages have to extend this class
 */
interface StorageInterface
{
	/**
	 * Retrieve request specified by id argument, if second argument is specified, array of requests from id to last
	 * will be returned
	 */
	public function retrieve($id = null, $last = null);

	/**
	 * Store request
	 */
	public function store(Request $request);
}
