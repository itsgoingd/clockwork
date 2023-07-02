<?php namespace Clockwork\Storage;

use Clockwork\Request\Request;

use Redis;
use RedisCluster;

class RedisStorage extends Storage
{
	// List of Request keys that need to be serialized before they can be stored in database
	protected $needsSerialization = [
		'headers', 'getData', 'postData', 'requestData', 'sessionData', 'authenticatedUser', 'cookies', 'middleware',
		'databaseQueries', 'cacheQueries', 'modelsActions', 'modelsRetrieved', 'modelsCreated', 'modelsUpdated',
		'modelsDeleted', 'redisCommands', 'queueJobs', 'timelineData', 'log', 'events', 'routes', 'notifications',
		'emailsData', 'viewsData', 'userData', 'subrequests', 'xdebug', 'commandArguments', 'commandArgumentsDefaults',
		'commandOptions', 'commandOptionsDefaults', 'jobPayload', 'jobOptions', 'testAsserts', 'parent',
		'clientMetrics', 'webVitals'
	];

	// Redis client instance
	protected $redis;

	// Metadata expiration time in minutes
	protected $expiration;

	public function __construct($connection, $expiration = null, $prefix = 'clockwork')
	{
		$this->redis = is_array($connection) ? $this->createClient($connection) : $connection;
		$this->prefix = $this->isCluster($connection) ? "{{$prefix}}" : $prefix;
		$this->expiration = $expiration === null ? 60 * 24 * 7 : $expiration;
	}

	protected function isCluster($connection)
	{
		return $connection instanceof RedisCluster
			|| is_array($connection) && isset($connection[0]) && is_array($connection[0]);
	}

	protected function createClient($connection)
	{
		return $this->isCluster($connection)
			? $this->createRedisClusterClient($connection)
			: $this->createRedisClient($connection);
	}

	protected function createRedisClient($connection)
	{
		$redis = new Redis();

		$redis->connect($connection['host'], $connection['port']);
		$redis->auth(array_filter([
			'user' => isset($connection['username']) ? $connection['username'] : null,
				'pass' => isset($connection['password']) ? $connection['password'] : null
			]));
		$redis->select($connection['database']);

		return $redis;
	}

	protected function createRedisClusterClient($connection)
	{
		return new RedisCluster(null, array_map(function ($hostConfig) {
			$host = "{$hostConfig['host']}:{$hostConfig['port']}";

			$query = array_filter([
				'database' => $hostConfig['database'],
				'password' => $hostConfig['password']
			]);

			if (count($query) > 0) {
				$host .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
			}

			return $host;
		}, $connection));
	}

	// Returns all requests
	public function all(Search $search = null)
	{
		if ($search->isNotEmpty()) {
			return $this->search($search);
		}

		return $this->loadRequests($this->redis->zRange($this->prefix('requests'), 0, -1));
	}

	// Return a single request by id
	public function find($id)
	{
		return $this->loadRequest($id);
	}

	// Return the latest request
	public function latest(Search $search = null)
	{
		if ($search->isNotEmpty()) {
			return $this->search('previous', $search, -1, 1);
		}
		
		$requests = $this->loadRequests($this->redis->zRange($this->prefix('requests'), -1, -1));
		return reset($requests);
	}

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null, Search $search = null)
	{
		$requestIndex = $this->redis->zRank($this->prefix('requests'), $id) - 1;
		
		if ($requestIndex < 0) return [];

		if ($search->isNotEmpty()) {
			return $this->search('previous', $search, $requestIndex, $count);
		}

		$startIndex = $count === null ? 0 : max($requestIndex - $count, 0);

		return $this->loadRequests($this->redis->zRange($this->prefix('requests'), $startIndex, $requestIndex));
	}

	// Return requests received after specified id, optionally limited to specified count
	public function next($id, $count = null, Search $search = null)
	{
		$requestIndex = $this->redis->zRank($this->prefix('requests'), $id);
		$indexLength = $this->redis->zCard($this->prefix('requests'));
		
		if ($requestIndex + 1 == $indexLength) return [];

		if ($search->isNotEmpty()) {
			return $this->search('next', $search, $requestIndex + 1, $count);
		}

		$endIndex = $count === null ? -1 : min($requestIndex + $count, $indexLength);

		return $this->loadRequests($this->redis->zRange($this->prefix('requests'), $requestIndex + 1, $endIndex));
	}

	// Store request
	public function store(Request $request)
	{
		$data = $request->toArray();

		foreach ($this->needsSerialization as $key) {
			$data[$key] = @json_encode($data[$key], \JSON_PARTIAL_OUTPUT_ON_ERROR);
		}

		$this->redis->multi();
		
		$this->redis->zAdd($this->prefix('requests'), $data['time'], $data['id']);
		$this->redis->hMSet($this->prefix($data['id']), $data);
		if ($this->expiration) $this->redis->expire($this->prefix($data['id']), $this->expiration * 60);

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

		$this->redis->hMSet($this->prefix($data['id']), $data);
		if ($this->expiration) $this->redis->expire($this->prefix($data['id']), $this->expiration * 60);

		$this->redis->exec();

		$this->cleanup();
	}

	// Cleanup old requests
	public function cleanup()
	{
		if ($this->expiration === false) return;

		$this->redis->zRemRangeByScore($this->prefix('requests'), 0, time() - ($this->expiration * 60));
	}

	// Search for requests based on the requests sorted set
	protected function search($direction, Search $search = null, $requestIndex = null, $count = null)
	{
		$searchTerm = array_unique(array_merge($search->uri, $search->name))[0];

		if ($requestIndex) {
			$ids = $direction == 'previous'
				? $this->redis->zRange($this->prefix('requests'), 0, $requestIndex)
				: $this->redis->zRange($this->prefix('requests'), $requestIndex, -1);
		} else {
			$ids = $this->redis->zRange($this->prefix('requests'), 0, -1);
		}

		if ($direction == 'previous') $ids = array_reverse($ids);

		$results = $this->redis->eval(static::SEARCH_SCRIPT, array_merge($ids, [ $searchTerm, $count ]));

		if ($direction == 'previous') $results = array_reverse($results);

		return $this->resultsToRequests($results);
	}

	// Prefix a key with the configured prefix
	protected function prefix($key)
	{
		return "{$this->prefix}:$key";
	}

	// Load a single request by id from Redis
	protected function loadRequest($id)
	{
		if (! $id) return;

		return $this->dataToRequest($this->redis->hGetAll($this->prefix($id)));
	}

	// Load multiple requests by ids from Redis
	protected function loadRequests($ids)
	{
		return array_filter(array_map(function ($id) { return $this->loadRequest($id); }, $ids));
	}

	// Returns a Request instance from a single Redis record
	protected function dataToRequest($data)
	{
		if (! $data) return;

		foreach ($this->needsSerialization as $key) {
			$data[$key] = json_decode($data[$key], true);
		}

		return new Request($data);
	}

	// Retusna Requests instances from search results
	protected function resultsToRequests($results)
	{
		return array_map(function($result) {
			return $this->dataToRequest(array_combine(array_column($result, 0), array_column($result, 1)));
		}, array_chunk($results, 2));
	}

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
}
