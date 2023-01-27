<?php

namespace Clockwork\Storage;

use Redis;
use Clockwork\Request\Request;

class RedisStorage extends Storage
{
	private Redis $redis;

	// Metadata expiration time in minutes
	protected $expiration;

	const REQUEST_KEY = '{Requests}';

	// List of Request keys that need to be serialized before they can be stored in database
	protected $needsSerialization = [
		'headers', 'getData', 'postData', 'requestData', 'sessionData', 'authenticatedUser', 'cookies', 'middleware',
		'databaseQueries', 'cacheQueries', 'modelsActions', 'modelsRetrieved', 'modelsCreated', 'modelsUpdated',
		'modelsDeleted', 'redisCommands', 'queueJobs', 'timelineData', 'log', 'events', 'routes', 'notifications',
		'emailsData', 'viewsData', 'userData', 'subrequests', 'xdebug', 'commandArguments', 'commandArgumentsDefaults',
		'commandOptions', 'commandOptionsDefaults', 'jobPayload', 'jobOptions', 'testAsserts', 'parent',
		'clientMetrics', 'webVitals'
	];

    public function __construct(string $host, ?string $password = null, int $port = 6379, $expiration = null)
    {
		$this->redis = new Redis();
		$this->redis->connect($host, $port);
		$this->redis->auth($password);
		$this->expiration = $expiration === null ? 60 * 24 * 7 : $expiration;
    }

	// Returns all requests
	public function all(Search $search = null)
    {
		$requestIds = $this->redis->zRange(self::REQUEST_KEY, 0, -1);

		$requests = [];
		foreach ($requestIds as $requestId) {
			$requests[] = $this->find($requestId);
		}
		return $requests;
    }

	// Return a single request by id
	public function find($id)
    {
		return $this->createRequest($this->redis->hGetAll($id));
    }

	// Return the latest request
	public function latest(Search $search = null)
    {
		$latestId = $this->redis->zRange(self::REQUEST_KEY, -1, -1);
		$data = $this->redis->hGetAll($latestId[0]);
		return $this->createRequest($data);
    }

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null, Search $search = null)
    {
		$endIndex = $this->redis->zRank(self::REQUEST_KEY, $id);
		$startIndex = $count === null ? 0 : $endIndex - $count;

		if ($startIndex < 0) {
			$startIndex = 0;
		}

		$requestIds = $this->redis->zRange(self::REQUEST_KEY, $startIndex, $endIndex);

		$requests = [];
		foreach ($requestIds as $requestId) {
			$requests[] = $this->find($requestId);
		}
		return $requests;
    }

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null, Search $search = null)
    {
		$startIndex = $this->redis->zRank(self::REQUEST_KEY, $id);
		$endIndex = $count === null ? -1 : $startIndex + $count;

		$finalIndex = $this->redis->zCard(self::REQUEST_KEY);
		if ($endIndex > $finalIndex) {
			$endIndex = $finalIndex;
		}

		$requestIds = $this->redis->zRange(self::REQUEST_KEY, $startIndex, $endIndex);

		$requests = [];
		foreach ($requestIds as $requestId) {
			$requests[] = $this->find($requestId);
		}
		return $requests;
    }

	// Store request
	public function store(Request $request)
    {
		$data = $request->toArray();
		foreach ($this->needsSerialization as $key) {
			$data[$key] = @json_encode($data[$key], \JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		$this->redis->multi();
		$this->redis->zAdd(self::REQUEST_KEY, $data['time'], $data['id']);
		$this->redis->hMSet($data['id'], $data);
		
		if ($this->expiration) {
			$this->redis->expire($data['id'], $this->expiration * 60);
		}
		$this->redis->exec();

		$this->cleanup();
    }

	// Update existing request
	public function update(Request $request)
    {
		$data = $request->toArray();
		foreach ($this->needsSerialization as $key) {
			$data[$key] = @json_encode($data[$key], \JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		$this->redis->multi();
		$this->redis->hMSet($data['id'], $data);

		if ($this->expiration) {
			$this->redis->expire($data['id'], $this->expiration * 60);
		}

		$this->redis->zAdd(self::REQUEST_KEY, ['xx'], $data['time'], $data['id']);

		$this->redis->exec();
		$this->cleanup();
    }

	// Cleanup old requests
	public function cleanup()
    {
		if ($this->expiration === false) {
			return;
		}

		$endTimeRange = time() - ($this->expiration * 60);
		$this->redis->zRemRangeByScore(self::REQUEST_KEY, 0, $endTimeRange);
    }

	private function createRequest(?array $data)
	{
		if ($data === null) {
			return null;
		}

		foreach ($this->needsSerialization as $key) {
			$data[$key] = @json_decode($data[$key], \JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		return new Request($data);
	}
}
