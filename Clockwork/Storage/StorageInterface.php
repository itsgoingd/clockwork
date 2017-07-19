<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;

/**
 * Interface for requests storage implementations
 */
interface StorageInterface
{
	// Returns all requests
	public function all();

	// Return a single request by id
	public function find($id);

	// Return the latest request
	public function latest();

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null);

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null);

	// Store request
	public function store(Request $request);

	// Cleanup old requests
	public function cleanup();
}
