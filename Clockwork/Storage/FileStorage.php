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

	// Compress the files using gzip
	protected $compress;

	// Metadata cleanup chance
	protected $cleanupChance = 100;

	// Index file handle
	protected $indexHandle;

	// Return new storage, takes path where to store files as argument, throws exception if path is not writable
	public function __construct($path, $dirPermissions = 0700, $expiration = null, $compress = false)
	{
		if (! file_exists($path)) {
			// directory doesn't exist, try to create one
			if (! @mkdir($path, $dirPermissions, true)) {
				throw new \Exception("Directory \"{$path}\" does not exist.");
			}

			// create default .gitignore, to ignore stored json files
			file_put_contents("{$path}/.gitignore", "*.json\nindex\n");
		}

		if (! is_writable($path)) {
			throw new \Exception("Path \"{$path}\" is not writable.");
		}

		if (! file_exists($indexFile = "{$path}/index")) {
			file_put_contents($indexFile, '');
		}

		$this->path = $path;
		$this->expiration = $expiration === null ? 60 * 24 * 7 : $expiration;
		$this->compress = $compress;
	}

	// Returns all requests
	public function all(Search $search = null)
	{
		return $this->searchIndexForward($search);
	}

	// Return a single request by id
	public function find($id)
	{
		return $this->loadRequest($id);
	}

	// Return the latest request
	public function latest(Search $search = null)
	{
		return $this->searchIndexBackward($search, null, 1);
	}

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null, Search $search = null)
	{
		return $this->searchIndexBackward($search, $id, $count);
	}

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null, Search $search = null)
	{
		return $this->searchIndexForward($search, $id, $count);
	}

	// Store request, requests are stored in JSON representation in files named <request id>.json in storage path
	public function store(Request $request)
	{
		$path = "{$this->path}/{$request->id}.json";
		$data = @json_encode($request->toArray(), \JSON_PARTIAL_OUTPUT_ON_ERROR);

		$this->compress
			? file_put_contents("{$path}.gz", gzcompress($data))
			: file_put_contents($path, $data);

		$this->updateIndex($request);
		$this->cleanup();
	}

	// Cleanup old requests
	public function cleanup($force = false, $count = 1)
	{
		if ($this->expiration === false || (! $force && rand(1, $this->cleanupChance) != 1)) return;

		$expirationTime = time() - ($this->expiration * 60);

		$old = $this->searchIndexBackward(new Search([ 'time' => [ "<{$expirationTime}" ] ]), null, $count);

		foreach ($old as $request) {
			$path = "{$this->path}/{$request->id}.json";
			@unlink($this->compress ? "{$path}.gz" : $path);
		}
	}

	protected function loadRequest($id)
	{
		$path = "{$this->path}/{$id}.json";

		if (! is_readable($this->compress ? "{$path}.gz" : $path)) return;

		$data = file_get_contents($this->compress ? "{$path}.gz" : $path);

		return new Request(json_decode($this->compress ? gzuncompress($data) : $data, true));
	}

	// Search index backward from specified ID or last record, with optional results count limit
	protected function searchIndexBackward(Search $search = null, $id = null, $count = 1)
	{
		return $this->searchIndex('previous', $search, $id, $count);
	}

	// Search index forward from specified ID or last record, with optional results count limit
	protected function searchIndexForward(Search $search = null, $id = null, $count = 1)
	{
		return $this->searchIndex('next', $search, $id, $count);
	}

	// Search index in specified direction from specified ID or last record, with optional results count limit
	protected function searchIndex($direction, Search $search = null, $id = null, $count = 1)
	{
		$this->openIndex($direction == 'previous' ? 'end' : 'start');

		if ($id) {
			while ($request = $this->readIndex($direction)) { if ($request->id == $id) break; }
		}

		$found = [];

		while ($request = $this->readIndex($direction)) {
			if (! $search || $search->matches($request)) $found[] = $this->loadRequest($request->id);
			if (count($found = array_filter($found)) === $count) return $found;
		}

		if ($count === 1) return reset($found);

		return $direction == 'next' ? $found : array_reverse($found);
	}

	// Open index file, optionally move file pointer to the end
	protected function openIndex($position = 'start')
	{
		$this->indexHandle = fopen("{$this->path}/index", 'r');

		if ($position == 'end') fseek($this->indexHandle, 0, SEEK_END);
	}

	// Read a line from index in the specified direction (next or previous)
	protected function readIndex($direction)
	{
		return $direction == 'next' ? $this->readNextIndex() : $this->readPreviousIndex();
	}

	// Read previous line from index
	protected function readPreviousIndex()
	{
		$position = ftell($this->indexHandle) - 1;

		if ($position <= 0) return;

		$line = '';

		// reads 1024B chunks of the file backwards from the current position, until a newline is found or we reach the top
		while ($position > 0) {
			// find next position to read from, make sure we don't read beyond file boundary
			$position -= $chunkSize = min($position, 1024);

			// read the chunk from the position
			fseek($this->indexHandle, $position);
			$chunk = fread($this->indexHandle, $chunkSize);

			// if a newline is found, append only the part after the last newline, otherwise we can append the whole chunk
			$line = ($newline = strrpos($chunk, "\n")) === false
				? $chunk . $line : substr($chunk, $newline + 1) . $line;

			// if a newline was found, fix the position so we read from that newline next time
			if ($newline !== false) $position += $newline + 1;

			// move file pointer to the correct position (revert fread, apply newline fix)
			fseek($this->indexHandle, $position);

			// if we reached a newline and put together a non-empty line we are done
			if ($newline !== false && $line) break;
		}

		return $this->makeRequestFromIndex(str_getcsv($line));
	}

	// Read next line from index
	protected function readNextIndex()
	{
		if (feof($this->indexHandle)) return;

		// File pointer is always at the start of the line, call extra fgets to skip current line
		fgets($this->indexHandle);
		$line = fgets($this->indexHandle);

		// Check if we read an empty line or reached the end of file
		if (! $line) return;

		// Reset the file pointer to the start of the read line
		fseek($this->indexHandle, ftell($this->indexHandle) - strlen($line));

		return $this->makeRequestFromIndex(str_getcsv($line));
	}

	protected function makeRequestFromIndex($record)
	{
		return new Request(array_combine(
			[ 'id', 'time', 'method', 'uri', 'controller', 'responseStatus', 'responseDuration' ], $record
		));
	}

	// Update index with a new request
	protected function updateIndex(Request $request)
	{
		fputcsv($handle = fopen("{$this->path}/index", 'a'), [
			$request->id,
			$request->time,
			$request->method,
			$request->uri,
			$request->controller,
			$request->responseStatus,
			$request->getResponseDuration()
		]);

		fclose($handle);
	}
}
