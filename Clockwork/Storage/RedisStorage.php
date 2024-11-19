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
		'emailsData', 'viewsData', 'userData', 'httpRequests', 'subrequests', 'xdebug', 'commandArguments',
		'commandArgumentsDefaults', 'commandOptions', 'commandOptionsDefaults', 'jobPayload', 'jobOptions', 'testAsserts',
		'parent', 'clientMetrics', 'webVitals'
	];

	// Redis client instance
	protected $redis;

	// Metadata expiration time in minutes
	protected $expiration;

	// Metedata keys prefix
	protected $prefix;

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

		$auth = array_filter([
			'user' => $connection['username'] ?? null,
			'pass' => $connection['password'] ?? null
		]);
		if (count($auth)) $redis->auth($auth);

		$redis->select($connection['database'] ?? 0);

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
	public function all(?Search $search = null)
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
	public function latest(?Search $search = null)
	{
		if ($search->isNotEmpty()) {
			return $this->search('previous', $search, -1, 1);
		}

		$requests = $this->loadRequests($this->redis->zRange($this->prefix('requests'), -1, -1));
		return reset($requests);
	}

	// Return requests received before specified id, optionally limited to specified count
	public function previous($id, $count = null, ?Search $search = null)
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
	public function next($id, $count = null, ?Search $search = null)
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
	protected function search($direction, ?Search $search = null, $requestIndex = null, $count = null)
	{
		if ($requestIndex) {
			$ids = $direction == 'previous'
				? $this->redis->zRange($this->prefix('requests'), 0, $requestIndex)
				: $this->redis->zRange($this->prefix('requests'), $requestIndex, -1);
		} else {
			$ids = $this->redis->zRange($this->prefix('requests'), 0, -1);
		}

		if ($direction == 'previous') $ids = array_reverse($ids);

		$keys = array_map(function ($id) { return $this->prefix($id); }, $ids);
		$args = array_map(
			function ($value) { return implode(',', $value); },
			[
				$search->type,
				$search->uri,
				$search->name,
				$search->controller,
				$search->method,
				$search->status,
				$search->time,
				array_map(function ($value) { return $value[0] . strtotime(substr($value, 1)); }, $search->received)
			]
		);

		$results = $this->redis->eval(static::SEARCH_SCRIPT, array_merge($keys, $args, [ $count ]), count($keys));

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
		return array_map(function ($result) {
			return $this->dataToRequest(array_combine(array_column(array_chunk($result, 2), 0), array_column(array_chunk($result, 2), 1)));
		}, $results);
	}

		const SEARCH_SCRIPT = <<<'SCRIPT'
#!lua flags=no-writes
local function splitInput(input)
	if not input then return {} end

	local result = {}
	for token in string.gmatch(input, "[^,]+") do
		table.insert(result, token)
	end

	return result
end

local function mergeArrays(a, b)
	local merged = {}

	for _, value in ipairs(a) do
		table.insert(merged, value)
	end

	for _, value in ipairs(b) do
		table.insert(merged, value)
	end

	return merged
end

local function isEmpty (array)
	for _, value in ipairs(array) do
		if value ~= '' then return false end
	end

	return true
end

local function checkStringCondition (request, fields, inputs, exact)
	if isEmpty(inputs) then return true end

	for _, field in ipairs(fields) do
		local value = request[field]

		for _, input in ipairs(inputs) do
			if (not exact and string.find(string.lower(value), string.lower(input))) then return true end
			if (exact and string.lower(value) == string.lower(input)) then return true end
		end
	end

	return false
end

local function checkNumberCondition (request, fields, inputs)
	if isEmpty(inputs) then return true end

	for _, field in ipairs(fields) do
		local value = tonumber(request[field])

		for _, input in ipairs(inputs) do
			if value == tonumber(input) then return true end

			local lowerLimit = tonumber(string.match(input, '^>(%d+%.?%d*)$'))
			if lowerLimit and value > lowerLimit then return true end

			local upperLimit = tonumber(string.match(input, '^<(%d+%.?%d*)$'))
			if upperLimit and value < upperLimit then return true end

			local rangeStart, rangeEnd = string.match(input, '^(%d+%.?%d*)-(%d+%.?%d*)$')
			if rangeStart and rangeEnd and tonumber(rangeStart) < value and value < tonumber(rangeEnd) then return true end
		end
	end

	return false
end

local results = {}

local input = {
	type = splitInput(ARGV[1]),
	uri = splitInput(ARGV[2]),
	name = splitInput(ARGV[3]),
	controller = splitInput(ARGV[4]),
	method = splitInput(ARGV[5]),
	status = splitInput(ARGV[6]),
	time = splitInput(ARGV[7]),
	received = splitInput(ARGV[8])
}
local limit = tonumber(ARGV[9])
local requestIds = KEYS

for _, requestId in ipairs(requestIds) do
	local requestData = redis.call('hgetall', requestId)
	local request = {}

	for i = 1, #requestData, 2 do request[requestData[i]] = requestData[i + 1] end

	if (
		checkStringCondition(request, {'type'}, input['type']) and
		checkStringCondition(request, {'uri', 'commandName', 'jobName', 'testName'}, mergeArrays(input['uri'], input['name'])) and
		checkStringCondition(request, {'controller'}, input['controller']) and
		checkStringCondition(request, {'method'}, input['method'], true) and
		checkNumberCondition(request, {'responseStatus', 'commandExitCode', 'jobStatus', 'testStatus'}, input['status']) and
		checkNumberCondition(request, {'responseDuration'}, input['time']) and
		checkNumberCondition(request, {'time'}, input['received'])
	)
	then
		table.insert(results, requestData)
	end

	if (limit ~= nil and #results >= limit) then
		return results
	end
end

return results
SCRIPT;
}
