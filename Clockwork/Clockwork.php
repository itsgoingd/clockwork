<?php namespace Clockwork;

use Clockwork\Authentication\AuthenticatorInterface;
use Clockwork\Authentication\NullAuthenticator;
use Clockwork\DataSource\DataSourceInterface;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\RequestType;
use Clockwork\Request\ShouldCollect;
use Clockwork\Request\ShouldRecord;
use Clockwork\Storage\StorageInterface;

// A central class implementing the core flow of the library
class Clockwork
{
	// Clockwork library version
	const VERSION = '5.1.6';

	// Array of data sources, these objects collect metadata for the current application run
	protected $dataSources = [];

	// Request object, data structure which stores metadata about the current application run
	protected $request;

	// Storage object, provides implementation for storing and retrieving request objects
	protected $storage;

	// Authenticator implementation, authenticates requests for clockwork metadata
	protected $authenticator;

	// An object specifying the rules for collecting requests
	protected $shouldCollect;

	// An object specifying the rules for recording requests
	protected $shouldRecord;

	// Create a new Clockwork instance with default request object, a storage implementation has to be additionally set
	public function __construct()
	{
		$this->request = new Request;
		$this->authenticator = new NullAuthenticator;

		$this->shouldCollect = new ShouldCollect;
		$this->shouldRecord = new ShouldRecord;
	}

	// Add a new data source
	public function addDataSource(DataSourceInterface $dataSource)
	{
		$this->dataSources[] = $dataSource;
		return $this;
	}

	// Resolve the current request, sending it through all data sources, finalizing log and timeline
	public function resolveRequest()
	{
		foreach ($this->dataSources as $dataSource) {
			$dataSource->resolve($this->request);
		}

		$this->request->log()->sort();
		$this->request->timeline()->finalize($this->request->time);

		return $this;
	}

	// Resolve the current request as a "command" type request with command-specific data
	public function resolveAsCommand($name, $exitCode = null, $arguments = [], $options = [], $argumentsDefaults = [], $optionsDefaults = [], $output = null)
	{
		$this->resolveRequest();

		$this->request->type = RequestType::COMMAND;
		$this->request->commandName = $name;
		$this->request->commandArguments = $arguments;
		$this->request->commandArgumentsDefaults = $argumentsDefaults;
		$this->request->commandOptions = $options;
		$this->request->commandOptionsDefaults = $optionsDefaults;
		$this->request->commandExitCode = $exitCode;
		$this->request->commandOutput = $output;

		return $this;
	}

	// Resolve the current request as a "queue-job" type request with queue-job-specific data
	public function resolveAsQueueJob($name, $description = null, $status = 'processed', $payload = [], $queue = null, $connection = null, $options = [])
	{
		$this->resolveRequest();

		$this->request->type = RequestType::QUEUE_JOB;
		$this->request->jobName = $name;
		$this->request->jobDescription = $description;
		$this->request->jobStatus = $status;
		$this->request->jobPayload = (new Serializer)->normalize($payload);
		$this->request->jobQueue = $queue;
		$this->request->jobConnection = $connection;
		$this->request->jobOptions = (new Serializer)->normalizeEach($options);

		return $this;
	}

	// Resolve the current request as a "test" type request with test-specific data, accepts test name, status, status
	// message in case of failure and array of ran asserts
	public function resolveAsTest($name, $status = 'passed', $statusMessage = null, $asserts = [])
	{
		$this->resolveRequest();

		$this->request->type = RequestType::TEST;
		$this->request->testName = $name;
		$this->request->testStatus = $status;
		$this->request->testStatusMessage = $statusMessage;

		foreach ($asserts as $assert) {
			$this->request->addTestAssert($assert['name'], $assert['arguments'], $assert['passed'], $assert['trace']);
		}

		return $this;
	}

	// Extends the request with an additional data form all data sources, which is not required for normal use
	public function extendRequest(Request $request = null)
	{
		foreach ($this->dataSources as $dataSource) {
			$dataSource->extend($request ?: $this->request);
		}

		return $this;
	}

	// Store the current request via configured storage implementation
	public function storeRequest()
	{
		return $this->storage->store($this->request);
	}

	// Reset all data sources to an empty state, clearing any collected data
	public function reset()
	{
		foreach ($this->dataSources as $dataSource) {
			$dataSource->reset();
		}

		return $this;
	}

	// Get or set the current request instance
	public function request(Request $request = null)
	{
		if (! $request) return $this->request;

		$this->request = $request;
		return $this;
	}

	// Get the log instance for the current request or log a new message
	public function log($level = null, $message = null, array $context = [])
	{
		if ($level) {
			return $this->request->log()->log($level, $message, $context);
		}

		return $this->request->log();
	}

	// Get the timeline instance for the current request
	public function timeline()
	{
		return $this->request->timeline();
	}

	// Shortcut to create a new event on the current timeline instance
	public function event($description, $data = [])
	{
		return $this->request->timeline()->event($description, $data);
	}

	// Configure which requests should be collected, can be called with arrey of options, a custom closure or with no
	// arguments for a fluent configuration api
	public function shouldCollect($shouldCollect = null)
	{
		if ($shouldCollect instanceof Closure) return $this->shouldCollect->callback($shouldCollect);

		if (is_array($shouldCollect)) return $this->shouldCollect->merge($shouldCollect);

		return $this->shouldCollect;
	}

	// Configure which requests should be recorded, can be called with arrey of options, a custom closure or with no
	// arguments for a fluent configuration api
	public function shouldRecord($shouldRecord = null)
	{
		if ($shouldRecord instanceof Closure) return $this->shouldRecord->callback($shouldRecord);

		if (is_array($shouldRecord)) return $this->shouldRecord->merge($shouldRecord);

		return $this->shouldRecord;
	}

	// Get or set all data sources at once
	public function dataSources($dataSources = null)
	{
		if (! $dataSources) return $this->dataSources;

		$this->dataSources = $dataSources;
		return $this;
	}

	// Get or set a storage implementation
	public function storage(StorageInterface $storage = null)
	{
		if (! $storage) return $this->storage;

		$this->storage = $storage;
		return $this;
	}

	// Get or set an authenticator implementation
	public function authenticator(AuthenticatorInterface $authenticator = null)
	{
		if (! $authenticator) return $this->authenticator;

		$this->authenticator = $authenticator;
		return $this;
	}

	// Forward any other method calls to the current request and log instances
	public function __call($method, $args)
	{
		if (in_array($method, [ 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ])) {
			return $this->request->log()->$method(...$args);
		}

		return $this->request->$method(...$args);
	}

	// DEPRECATED The following apis are deprecated and will be removed in a future version

	// Get all added data sources
	public function getDataSources()
	{
		return $this->dataSources;
	}

	// Get the current request instance
	public function getRequest()
	{
		return $this->request;
	}

	// Set the current request instance
	public function setRequest(Request $request)
	{
		$this->request = $request;
		return $this;
	}

	// Get a storage implementation
	public function getStorage()
	{
		return $this->storage;
	}

	// Set a storage implementation
	public function setStorage(StorageInterface $storage)
	{
		$this->storage = $storage;
		return $this;
	}

	// Get an authenticator implementation
	public function getAuthenticator()
	{
		return $this->authenticator;
	}

	// Set an authenticator implementation
	public function setAuthenticator(AuthenticatorInterface $authenticator)
	{
		$this->authenticator = $authenticator;
		return $this;
	}
}
