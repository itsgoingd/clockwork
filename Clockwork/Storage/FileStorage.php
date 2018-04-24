<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;
use Clockwork\Storage\Storage;

/**
 * Simple file based storage for requests
 */
class FileStorage extends Storage
{
	// Path where files are stored
	protected $path;

	// Metadata expiration time in minutes
	protected $expiration;

	// Metadata cleanup chance
	protected $cleanupChance = 100;

	// Return new storage, takes path where to store files as argument, throws exception if path is not writable
	public function __construct($path, $dirPermissions = 0700, $expiration = null)
	{
		if (! file_exists($path)) {
			// directory doesn't exist, try to create one
			if (! @mkdir($path, $dirPermissions, true)) {
				throw new \Exception("Directory \"{$path}\" does not exist.");
			}

			// create default .gitignore, to ignore stored json files
			file_put_contents("{$path}/.gitignore", "*.json\n");
		}

		if (! is_writable($path)) {
			throw new \Exception("Path \"{$path}\" is not writable.");
		}

		$this->path = $path;
		$this->expiration = $expiration === null ? 60 * 24 * 7 : $expiration;
	}

	// Returns all requests
	public function all()
	{
		return $this->idsToRequests($this->ids());
	}

	// Return a single request by id
	public function find($id)
	{
		return $this->idsToRequests([ $id ])[0];
	}

	// Return the latest request
	public function latest()
	{
		$ids = $this->ids();
		return $this->find(end($ids));
	}

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null)
	{
		$ids = $this->ids();

		$lastIndex = array_search($id, $ids) - 1;
		$firstIndex = $count && $lastIndex - $count > 0 ? $lastIndex - $count : 0;

		return $this->idsToRequests(array_slice($ids, $firstIndex, $lastIndex - $firstIndex));
	}

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null)
	{
		$ids = $this->ids();

		$firstIndex = array_search($id, $ids) + 1;
		$lastIndex = $count && $firstIndex + $count < count($ids) ? $firstIndex + $count : count($ids);

		return $this->idsToRequests(array_slice($ids, $firstIndex, $lastIndex - $firstIndex));
	}

	// Store request, requests are stored in JSON representation in files named <request id>.json in storage path
	public function store(Request $request)
	{
		file_put_contents(
			"{$this->path}/{$request->id}.json",
			@json_encode($this->applyFilter($request->toArray()), defined('JSON_PARTIAL_OUTPUT_ON_ERROR') ? \JSON_PARTIAL_OUTPUT_ON_ERROR : 0)
		);

		$this->cleanup();
	}

	// Cleanup old requests
	public function cleanup($force = false)
	{
		if ($this->expiration === false || (! $force && rand(1, $this->cleanupChance) != 1)) return;

		$expirationTime = time() - ($this->expiration * 60);

		$ids = array_filter($this->ids(), function ($id) use ($expirationTime) {
			preg_match('#(?<time>\d+\-\d+)\-\d+#', $id, $matches);
			return ! isset($matches['time']) || str_replace('-', '.', $matches['time']) < $expirationTime;
		});

		foreach ($ids as $id) {
			@unlink("{$this->path}/{$id}.json");
		}
	}

	// Returns all request ids
	protected function ids()
	{
		return array_map(function ($path) {
			preg_match('#/(?<id>[^/]+?)\.json$#', $path, $matches);
			return $matches['id'];
		}, glob("{$this->path}/*.json"));
	}

	// Returns array of Request instances from passed ids
	protected function idsToRequests($ids)
	{
		return array_map(function ($id) {
			if (is_readable("{$this->path}/{$id}.json")) {
				return new Request(json_decode(file_get_contents("{$this->path}/{$id}.json"), true));
			}
		}, $ids);
	}
}
