<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;
use Clockwork\Storage\Storage;

// File based storage for requests
class FileStorage extends Storage
{
	// Path where files are stored
	protected $path;

	// Path permissions
	protected $pathPermissions;

	// Metadata expiration time in minutes
	protected $expiration;

	// Compress the files using gzip
	protected $compress;

	// Metadata cleanup chance
	protected $cleanupChance = 100;

	// Index file handle
	protected $indexHandle;

	// Return new storage, takes path where to store files as argument
	public function __construct($path, $pathPermissions = 0700, $expiration = null, $compress = false)
	{
		$this->path = $path;
		$this->pathPermissions = $pathPermissions;
		$this->expiration = $expiration === null ? 60 * 24 * 7 : $expiration;
		$this->compress = $compress;
	}

	// Returns all requests
	public function all(?Search $search = null)
	{
		return $this->loadRequests($this->searchIndexForward($search));
	}

	// Return a single request by id
	public function find($id)
	{
		return $this->loadRequest($id);
	}

	// Return the latest request
	public function latest(?Search $search = null)
	{
		$requests = $this->loadRequests($this->searchIndexBackward($search, null, 1));
		return reset($requests);
	}

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null, ?Search $search = null)
	{
		return $this->loadRequests($this->searchIndexBackward($search, $id, $count));
	}

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null, ?Search $search = null)
	{
		return $this->loadRequests($this->searchIndexForward($search, $id, $count));
	}

	// Store request, requests are stored in JSON representation in files named <request id>.json in storage path,
	// throws exception if path is not writable
	public function store(Request $request, $skipIndex = false)
	{
		$this->ensurePathIsWritable();

		$path = "{$this->path}/{$request->id}.json";
		$data = @json_encode($request->toArray(), \JSON_PARTIAL_OUTPUT_ON_ERROR);

		$this->compress
			? file_put_contents("{$path}.gz", gzcompress($data))
			: file_put_contents($path, $data . PHP_EOL);

		if (! $skipIndex) $this->updateIndex($request);

		$this->cleanup();
	}

	// Update existing request
	public function update(Request $request)
	{
		return $this->store($request, true);
	}

	// Cleanup old requests
	public function cleanup($force = false)
	{
		if ($this->expiration === false || (! $force && rand(1, $this->cleanupChance) != 1)) return;

		$this->openIndex('start', true, true); // reopen index with lock

		$expirationTime = time() - ($this->expiration * 60);

		$old = $this->searchIndexForward(
			new Search([ 'received' => [ '<' . date('c', $expirationTime) ] ], [ 'stopOnFirstMismatch' => true ])
		);

		if (! count($old)) return $this->closeIndex(true);

		$this->readPreviousIndex();
		$this->trimIndex();
		$this->closeIndex(true); // explicitly close index to unlock asap

		foreach ($old as $id) {
			$path = "{$this->path}/{$id}.json";
			@unlink($this->compress ? "{$path}.gz" : $path);
		}
	}

	// Load a single request by id from filesystem
	protected function loadRequest($id)
	{
		$path = "{$this->path}/{$id}.json";

		if (! is_readable($this->compress ? "{$path}.gz" : $path)) return;

		$data = file_get_contents($this->compress ? "{$path}.gz" : $path);

		return new Request(json_decode($this->compress ? gzuncompress($data) : $data, true));
	}

	// Load multiple requests by ids from filesystem
	protected function loadRequests($ids)
	{
		return array_filter(array_map(function ($id) { return $this->loadRequest($id); }, $ids));
	}

	// Search index backward from specified ID or last record, with optional results count limit
	protected function searchIndexBackward(?Search $search = null, $id = null, $count = null)
	{
		return $this->searchIndex('previous', $search, $id, $count);
	}

	// Search index forward from specified ID or last record, with optional results count limit
	protected function searchIndexForward(?Search $search = null, $id = null, $count = null)
	{
		return $this->searchIndex('next', $search, $id, $count);
	}

	// Search index in specified direction from specified ID or last record, with optional results count limit
	protected function searchIndex($direction, ?Search $search = null, $id = null, $count = null)
	{
		$this->openIndex($direction == 'previous' ? 'end' : 'start', false, true);

		if ($id) {
			while ($request = $this->readIndex($direction)) { if ($request->id == $id) break; }
		}

		$found = [];

		while ($request = $this->readIndex($direction)) {
			if (! $search || $search->matches($request)) {
				$found[] = $request->id;
			} elseif ($search->stopOnFirstMismatch) {
				break;
			}

			if ($count && count($found) == $count) break;
		}

		return $direction == 'next' ? $found : array_reverse($found);
	}

	// Open index file, optionally lock or move file pointer to the end, existing handle will be returned by default
	protected function openIndex($position = 'start', $lock = false, $force = false)
	{
		if ($this->indexHandle) {
			if (! $force) return;
			$this->closeIndex();
		}

		$this->indexHandle = fopen("{$this->path}/index", 'r');

		if ($lock) flock($this->indexHandle, LOCK_EX);
		if ($position == 'end') fseek($this->indexHandle, 0, SEEK_END);
	}

	// Close index file, optionally unlock
	protected function closeIndex($lock = false)
	{
		if ($lock) flock($this->indexHandle, LOCK_UN);
		fclose($this->indexHandle);

		$this->indexHandle = null;
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

		// File pointer is always kept at the start of the "next" record, scroll back to the end of "previous" record
		fseek($this->indexHandle, $position - 1);

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
			if ($newline !== false) break;
		}

		return $this->makeRequestFromIndex(str_getcsv($line, ',', '"', ''));
	}

	// Read next line from index
	protected function readNextIndex()
	{
		if (feof($this->indexHandle)) return;

		// File pointer is always at the start of the "next" record
		$line = fgets($this->indexHandle);

		// Check if we read an empty line or reached the end of file
		if ($line === false) return;

		return $this->makeRequestFromIndex(str_getcsv($line, ',', '"', ''));
	}

	// Trim index file from beginning to current position (including)
	protected function trimIndex()
	{
		// Read the rest of the index file
		$trimmedLength = filesize("{$this->path}/index") - ftell($this->indexHandle);
		$trimmed = $trimmedLength > 0 ? fread($this->indexHandle, $trimmedLength) : '';

		// Rewrite the index file with a trimmed version
		file_put_contents("{$this->path}/index", $trimmed);
	}

	// Create an incomplete request from index data
	protected function makeRequestFromIndex($record)
	{
		$type = $record[7] ?? 'response';

		if ($type == 'command') {
			$nameField = 'commandName';
		} elseif ($type == 'queue-job') {
			$nameField = 'jobName';
		} elseif ($type == 'test') {
			$nameField = 'testName';
		} else {
			$nameField = 'uri';
		}

		return new Request(array_combine(
			[ 'id', 'time', 'method', $nameField, 'controller', 'responseStatus', 'responseDuration', 'type' ],
			array_slice($record, 0, 8) + [ null, null, null, null, null, null, null, 'response' ]
		));
	}

	// Update index with a new request
	protected function updateIndex(Request $request)
	{
		$handle = fopen("{$this->path}/index", 'a');

		if (! $handle) return;

		if (! flock($handle, LOCK_EX)) return fclose($handle);

		if ($request->type == 'command') {
			$nameField = 'commandName';
		} elseif ($request->type == 'queue-job') {
			$nameField = 'jobName';
		} elseif ($request->type == 'test') {
			$nameField = 'testName';
		} else {
			$nameField = 'uri';
		}

		fputcsv($handle, [
			$request->id,
			$request->time,
			$request->method,
			$request->$nameField,
			$request->controller,
			$request->responseStatus,
			$request->getResponseDuration(),
			$request->type
		], ',', '"', PHP_VERSION_ID >= 70400 ? '' : '\\');

		flock($handle, LOCK_UN);
		fclose($handle);
	}

	// Ensure the metadata path is writable and initialize it if it doesn't exist, throws exception if it is not writable
	protected function ensurePathIsWritable()
	{
		if (! is_dir($this->path)) {
			// directory doesn't exist, try to create one
			if (! @mkdir($this->path, $this->pathPermissions, true)) {
				throw new \Exception("Directory \"{$this->path}\" does not exist.");
			}

			// create default .gitignore, to ignore stored json files
			file_put_contents("{$this->path}/.gitignore", "*.json\n*.json.gz\nindex\n");
		} elseif (! is_writable($this->path)) {
			throw new \Exception("Path \"{$this->path}\" is not writable.");
		}

		if (! is_file($indexFile = "{$this->path}/index")) {
			file_put_contents($indexFile, '');
		} elseif (! is_writable($indexFile)) {
			throw new \Exception("Path \"{$indexFile}\" is not writable.");
		}
	}
}
