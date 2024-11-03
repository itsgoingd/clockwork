<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;
use Clockwork\Support\Symfony\ProfileTransformer;

use Symfony\Component\HttpKernel\Profiler\Profiler;

// Storage wrapping Symfony profiler
class SymfonyStorage extends FileStorage
{
	// Symfony profiler instance
	protected $profiler;

	// Symfony profiler path
	protected $path;

	// Create a new instance, takes Symfony profiler instance and path as argument
	public function __construct(Profiler $profiler, $path)
	{
		$this->profiler = $profiler;
		$this->path = $path;
	}

	// Store request, no-op since this is read-only storage implementation
	public function store(Request $request, $skipIndex = false)
	{
		return;
	}

	// Cleanup old requests, no-op since this is read-only storage implementation
	public function cleanup($force = false)
	{
		return;
	}

	protected function loadRequest($token)
	{
		return ($profile = $this->profiler->loadProfile($token)) ? (new ProfileTransformer)->transform($profile) : null;
	}

	// Open index file, optionally move file pointer to the end
	protected function openIndex($position = 'start', $lock = null, $force = null)
	{
		$this->indexHandle = fopen("{$this->path}/index.csv", 'r');

		if ($position == 'end') fseek($this->indexHandle, 0, SEEK_END);
	}

	protected function makeRequestFromIndex($record)
	{
		return new Request(array_combine(
			[ 'id', 'method', 'uri', 'time', 'parent', 'responseStatus' ],
			[ $record[0], $record[2], $record[3], $record[4], $record[5], $record[6] ]
		));
	}
}
