<?php

namespace Clockwork\Storage;

use Redis;
use RedisCluster;
use Clockwork\Request\Request;

class RedisStorage extends Storage
{
	const REQUEST_HASHTAG = ':{Snowplow}';
	const REQUESTS_KEY = 'Requests';

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

	private $redis;

	// Metadata expiration time in minutes
	protected $expiration;

	private $requestsKey;

    public function __construct(array $config, ?int $expiration)
    {
		$this->expiration = $expiration === null ? 60 * 24 * 7 : $expiration;
		$this->requestsKey =  self::REQUESTS_KEY . self::REQUEST_HASHTAG;

		array_key_exists('clusters', $config) ?
			$this->createClusteredRedisClient($config['clusters']['default']):
			$this->createRedisClient($config['default']);
    }

	private function createRedisClient(array $connectionConfig)
	{
		$this->redis = new Redis();
		$this->redis->connect($connectionConfig['host'], $connectionConfig['port']);
		$this->redis->auth($this->getCredentials($connectionConfig));
		$this->redis->select($connectionConfig['database']);
	}

	private function createClusteredRedisClient(array $hostsConfig)
	{
		$hosts = [];
		foreach ($hostsConfig as $hostConfig) {
			$hosts[] = $this->formatHost($hostConfig);
		}
    
		\Log::debug(json_encode($hosts, JSON_PRETTY_PRINT));
		$this->redis = new RedisCluster(null, $hosts);
	}

	private function formatHost(array $hostConfig)
	{
		$host = $hostConfig['host'] . ':' . $hostConfig['port'];

		$query = [];
		if (array_key_exists('database', $hostConfig)) {
			$query['database'] = $hostConfig['database'];
		}

		if (array_key_exists('pasword', $hostConfig)) {
			$query['password'] = $hostConfig['password'];
		}

		if (count($query) > 0) {
			$host .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		}

		return $host;
	}

	private function getCredentials(array $connectionConfig)
	{
		$credentials = [];
		
		if (array_key_exists('username', $connectionConfig)) {
			$credentials['user'] = $connectionConfig['username'];
		}

		if (array_key_exists('password', $connectionConfig)) {
			$credentials['pass'] = $connectionConfig['password'];
		}

		return $credentials;
	}

	// Returns all requests
	public function all(Search $search = null)
    {
		if ($search->isNotEmpty()) {
			return $this->search($search);
		}

		$requestIds = $this->redis->zRange($this->requestsKey, 0, -1);

		$requests = [];
		foreach ($requestIds as $requestId) {
			$requests[] = $this->findWithHashtag($requestId);
		}
		return $requests;
    }

	// Return a single request by id
	public function find($id)
    {
		return $this->findWithHashtag($id . self::REQUEST_HASHTAG);
    }

	private function findWithHashtag($idWithHashtag)
	{
		return $this->createRequest($this->redis->hGetAll($idWithHashtag));
	}

	// Return the latest request
	public function latest(Search $search = null)
    {
		if ($search->isNotEmpty()) {
			return $this->search($search, 1, null, true, true);
		}
		
		$latestId = $this->redis->zRange($this->requestsKey, -1, -1);
		
		if (count($latestId) == 0) {
			return [];
		}

		return $this->findWithHashtag($latestId[0]);
    }

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null, Search $search = null)
    {
		$requestIndex = $this->redis->zRank($this->requestsKey, $id . self::REQUEST_HASHTAG) - 1;
		
		if ($requestIndex < 0) {
			return [];
		}

		if ($search->isNotEmpty()) {
			return $this->search($search, $count, $requestIndex, true, true);
		}
		
		$startIndex = $count === null ? 0 : $requestIndex - $count;

		if ($startIndex < 0) {
			$startIndex = 0;
		}

		$requestIds = $this->redis->zRange($this->requestsKey, $startIndex, $requestIndex);

		$requests = [];
		foreach ($requestIds as $requestId) {
			$requests[] = $this->findWithHashtag($requestId);
		}
		return $requests;
    }

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null, Search $search = null)
    {
		$requestIndex = $this->redis->zRank($this->requestsKey, $id . self::REQUEST_HASHTAG);
		$indexLength = $this->redis->zCard($this->requestsKey);
		
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

		$requestIds = $this->redis->zRange($this->requestsKey, $requestIndex + 1, $endIndex);

		$requests = [];
		foreach ($requestIds as $requestId) {
			$requests[] = $this->findWithHashtag($requestId);
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
		$this->redis->zAdd($this->requestsKey, $data['time'], $data['id'] . self::REQUEST_HASHTAG);
		$this->redis->hMSet($data['id'] . self::REQUEST_HASHTAG, $data);
		
		if ($this->expiration) {
			$this->redis->expire($data['id'] . self::REQUEST_HASHTAG, $this->expiration * 60);
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
		$this->redis->hMSet($data['id'] . self::REQUEST_HASHTAG, $data);

		if ($this->expiration) {
			$this->redis->expire($data['id'] . self::REQUEST_HASHTAG, $this->expiration * 60);
		}

		$this->redis->zAdd($this->requestsKey, ['xx'], $data['time'], $data['id'] . self::REQUEST_HASHTAG);

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
		$this->redis->zRemRangeByScore($this->requestsKey, 0, $endTimeRange);
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
				$this->redis->zRange($this->requestsKey, 0, $requestIndex) :
				$this->redis->zRange($this->requestsKey, $requestIndex, -1);
		} else {
			$requestIds = $this->redis->zRange($this->requestsKey, 0, -1);
		}

		if ($searchReversed) {
			$requestIds = array_reverse($requestIds);
		}

		$scriptResults = $this->redis->eval(
			self::SEARCH_SCRIPT,
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
