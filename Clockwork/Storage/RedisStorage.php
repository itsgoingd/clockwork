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

	const SEARCH_SCRIPT = "#!lua flags=no-writes
		local searchResults = {}
		local resultNumber = 1

		local searchTerm = ARGV[1]
		local resultsLimit = tonumber(ARGV[2])
		local requestIds = KEYS

		local keysToSearch = {
			['uri'] = true,
			['commandName'] = true,
			['jobName'] = true,
			['testName'] = true,
		}

		local function search (request, search, ...)
			local keysToSearch = (...)
			for index, value in pairs(request) do
				if (keysToSearch[value]) then
					local actualValue = request[index+1]
					local search = string.find(actualValue, search, 1, true)

					if (search ~= nil) then
						return true
					end
				end
			end
			return false
		end
		
		for i, requestId in pairs(requestIds) do
			local request = redis.call('hgetall', requestId)
			if (search(request, searchTerm, keysToSearch)) then
				searchResults[resultNumber] = request
				resultNumber = resultNumber + 1

				if (resultsLimit ~= nil and resultNumber > resultsLimit) then
					return searchResults
				end
			end
		end
		return searchResults";

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
		if ($search->isNotEmpty()) {
			return $this->search($search);
		}

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
		if ($search->isNotEmpty()) {
			return $this->search($search, 1, null, true, true);
		}
		
		$latestId = $this->redis->zRange(self::REQUEST_KEY, -1, -1);
		
		if (count($latestId) == 0) {
			return [];
		}

		$data = $this->redis->hGetAll($latestId[0]);
		return $this->createRequest($data);
    }

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null, Search $search = null)
    {
		$requestIndex = $this->redis->zRank(self::REQUEST_KEY, $id) - 1;

		if ($search->isNotEmpty()) {
			return $this->search($search, $count, $requestIndex, true, true);
		}
		
		$startIndex = $count === null ? 0 : $requestIndex - $count;

		if ($startIndex < 0) {
			$startIndex = 0;
		}

		$requestIds = $this->redis->zRange(self::REQUEST_KEY, $startIndex, $requestIndex);

		$requests = [];
		foreach ($requestIds as $requestId) {
			$requests[] = $this->find($requestId);
		}
		return $requests;
    }

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null, Search $search = null)
    {
		$requestIndex = $this->redis->zRank(self::REQUEST_KEY, $id);
		$indexLength = $this->redis->zCard(self::REQUEST_KEY);
		
		if ($requestIndex + 1 == $indexLength) {
			return [];
		}

		if ($search->isNotEmpty()) {
			return $this->search($search, $count, $requestIndex + 1, false);
		}
		
		$endIndex = $count === null ? -1 : $requestIndex + $count;

		if ($endIndex > $indexLength) {
			$endIndex = $indexLength;
		}

		$requestIds = $this->redis->zRange(self::REQUEST_KEY, $requestIndex + 1, $endIndex);

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
	
	private function search(
		Search $search,
		?int $count = null,
		$requestIndex = null,
		bool $searchBefore = true,
		bool $searchReversed = false
	)
	{
		$searchTerm = array_unique(array_merge($search->uri, $search->name))[0];

		if ($requestIndex !== null) {
			$requestIds = $searchBefore ?
				$this->redis->zRange(self::REQUEST_KEY, 0, $requestIndex) :
				$this->redis->zRange(self::REQUEST_KEY, $requestIndex, -1);
		} else {
			$requestIds = $this->redis->zRange(self::REQUEST_KEY, 0, -1);
		}

		if ($searchReversed) {
			$requestIds = array_reverse($requestIds);
		}

		$scriptSha = $this->redis->script('load', self::SEARCH_SCRIPT);
		$scriptResults = $this->redis->evalSha(
			$scriptSha,
			[
				...$requestIds,
				$searchTerm,
				$count,
			],
			count($requestIds)
		);

		if ($searchReversed) {
			$scriptResults = array_reverse($scriptResults);
		}
		
		return $this->getRequestsFromScriptResults($scriptResults);
	}

	private function getRequestsFromScriptResults($scriptResults)
	{
		if (!$scriptResults) {
			return [];
		}

		$results = [];
		foreach ($scriptResults as $scriptResult) {
			$result = [];
			for ($index = 0; $index < count($scriptResult); $index += 2) {
				$result[$scriptResult[$index]] = $scriptResult[$index+1];
			}
			$results[] = $this->createRequest($result);
		}
		return $results;
	}
}
