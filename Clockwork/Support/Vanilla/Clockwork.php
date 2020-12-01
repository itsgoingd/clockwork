<?php namespace Clockwork\Support\Vanilla;

use Clockwork\Clockwork as BaseClockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\PsrMessageDataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Helpers\StackFilter;
use Clockwork\Request\IncomingRequest;
use Clockwork\Storage\FileStorage;
use Clockwork\Storage\Search;
use Clockwork\Storage\SqlStorage;

use Psr\Http\Message\ServerRequestInterface as PsrRequest;
use Psr\Http\Message\ResponseInterface as PsrResponse;

// Clockwork integration for vanilla php and unsupported frameworks
class Clockwork
{
	// Clockwork config
	protected $config;
	// Clockwork instance
	protected $clockwork;

	// PSR-7 request and response
	protected $psrRequest;
	protected $psrResponse;

	// Whether the headers were already sent (header can be sent manually)
	protected $headersSent = false;

	// Static instance when used as singleton
	protected static $defaultInstance;

	// Create new instance, takes an additional config
	public function __construct($config = [])
	{
		$this->config = array_merge(include __DIR__ . '/config.php', $config);

		$this->clockwork = new BaseClockwork;

		$this->clockwork->addDataSource(new PhpDataSource);
		$this->clockwork->storage($this->makeStorage());

		$this->configureSerializer();
		$this->configureShouldCollect();
		$this->configureShouldRecord();

		if ($this->config['register_helpers']) include __DIR__ . '/helpers.php';
	}

	// Initialize a singleton instance, takes an additional config
	public static function init($config = [])
	{
		return static::$defaultInstance = new static($config);
	}

	// Return the singleton instance
	public static function instance()
	{
		return static::$defaultInstance;
	}

	// Resolves and records the current request and sends Clockwork headers, should be called at the end of app
	// execution, return PSR-7 response if one was set
	public function requestProcessed()
	{
		if (! $this->config['enable'] && ! $this->config['collect_data_always']) return $this->psrResponse;

		if (! $this->clockwork->shouldCollect()->filter($this->incomingRequest())) return $this->psrResponse;
		if (! $this->clockwork->shouldRecord()->filter($this->clockwork->request())) return $this->psrResponse;

		$this->clockwork->resolveRequest()->storeRequest();

		if (! $this->config['enable']) return $this->psrResponse;

		$this->sendHeaders();

		if (($eventsCount = $this->config['server_timing']) !== false) {
			$this->setHeader('Server-Timing', ServerTiming::fromRequest($this->clockwork->request(), $eventsCount)->value());
		}

		return $this->psrResponse;
	}

	// Resolves and records the current request as a command, should be called at the end of app execution
	public function commandExecuted($name, $exitCode = null, $arguments = [], $options = [], $argumentsDefaults = [], $optionsDefaults = [], $output = null)
	{
		if (! $this->config['enable'] && ! $this->config['collect_data_always']) return;

		if (! $this->clockwork->shouldRecord()->filter($this->clockwork->request())) return;

		$this->clockwork
			->resolveAsCommand($name, $exitCode, $arguments, $options, $argumentsDefaults, $optionsDefaults, $output)
			->storeRequest();
	}

	// Resolves and records the current request as a queue job, should be called at the end of app execution
	public function queueJobExecuted($name, $description = null, $status = 'processed', $payload = [], $queue = null, $connection = null, $options = [])
	{
		if (! $this->config['enable'] && ! $this->config['collect_data_always']) return;

		if (! $this->clockwork->shouldRecord()->filter($this->clockwork->request())) return;

		$this->clockwork
			->resolveAsQueueJob($name, $description, $status, $payload, $queue, $connection, $options)
			->storeRequest();
	}

	// Manually send the Clockwork headers, this should be manually called only when the headers need to be sent early
	// in the request processing
	public function sendHeaders()
	{
		if (! $this->config['enable'] || $this->headersSent) return;

		$this->headersSent = true;

		$this->setHeader('X-Clockwork-Id', $this->request()->id);
		$this->setHeader('X-Clockwork-Version', BaseClockwork::VERSION);

		if ($this->config['api'] != '/__clockwork/') {
			$this->setHeader('X-Clockwork-Path', $this->config['api']);
		}

		foreach ($this->config['headers'] as $headerName => $headerValue) {
			$this->setHeader("X-Clockwork-Header-{$headerName}", $headerValue);
		}
	}

	// Sends http response with metadata based on the passed Clockwork REST api request
	public function returnMetadata($request = null)
	{
		if (! $this->config['enable']) return;

		$this->setHeader('Content-Type', 'application/json');

		echo json_encode($this->getMetadata($request), \JSON_PARTIAL_OUTPUT_ON_ERROR);
	}

	// Returns metadata based on the passed Clockwork REST api request
	public function getMetadata($request = null)
	{
		if (! $this->config['enable']) return;

		if (! $request) $request = isset($_GET['request']) ? $_GET['request'] : '';

		preg_match('#(?<id>[0-9-]+|latest)(?:/(?<direction>next|previous))?(?:/(?<count>\d+))?#', $request, $matches);

		$id = isset($matches['id']) ? $matches['id'] : null;
		$direction = isset($matches['direction']) ? $matches['direction'] : null;
		$count = isset($matches['count']) ? $matches['count'] : null;

		if ($direction == 'previous') {
			$data = $this->clockwork->storage()->previous($id, $count, Search::fromRequest($_GET));
		} elseif ($direction == 'next') {
			$data = $this->clockwork->storage()->next($id, $count, Search::fromRequest($_GET));
		} elseif ($id == 'latest') {
			$data = $this->clockwork->storage()->latest(Search::fromRequest($_GET));
		} else {
			$data = $this->clockwork->storage()->find($id);
		}

		if (preg_match('#(?<id>[0-9-]+|latest)/extended#', $request)) {
			$this->clockwork->extendRequest($data);
		}

		if ($data) {
			$data = is_array($data) ? array_map(function ($item) { return $item->toArray(); }, $data) : $data->toArray();
		}

		return $data;
	}

	// Use a PSR-7 request and response instances instead of vanilla php HTTP apis
	public function usePsrMessage(PsrRequest $request, PsrResponse $response = null)
	{
		$this->psrRequest = $request;
		$this->psrResponse = $response;

		$this->clockwork->addDataSource(new PsrMessageDataSource($request, $response));

		return $this;
	}

	// Make a storage implementation based on user configuration
	protected function makeStorage()
	{
		if ($this->config['storage'] == 'sql') {
			$database = $this->config['storage_sql_database'];
			$table = $this->config['storage_sql_table'];

			$storage = new SqlStorage(
				$this->config['storage_sql_database'],
				$this->config['storage_sql_table'],
				$this->config['storage_sql_username'],
				$this->config['storage_sql_password'],
				$this->config['storage_expiration']
			);
		} else {
			$storage = new FileStorage(
				$this->config['storage_files_path'],
				0700,
				$this->config['storage_expiration'],
				$this->config['storage_files_compress']
			);
		}

		return $storage;
	}

	// Configure serializer defaults based on user configuration
	protected function configureSerializer()
	{
		Serializer::defaults([
			'limit'       => $this->config['serialization_depth'],
			'blackbox'    => $this->config['serialization_blackbox'],
			'traces'      => $this->config['stack_traces']['enabled'],
			'tracesSkip'  => StackFilter::make()
				->isNotVendor(array_merge(
					$this->config['stack_traces']['skip_vendors'],
					[ 'itsgoingd', 'laravel', 'illuminate' ]
				))
				->isNotNamespace($this->config['stack_traces']['skip_namespaces'])
				->isNotFunction([ 'call_user_func', 'call_user_func_array' ])
				->isNotClass($this->config['stack_traces']['skip_classes']),
			'tracesLimit' => $this->config['stack_traces']['limit']
		]);
	}

	// Configure should collect rules based on user configuration
	public function configureShouldCollect()
	{
		$this->clockwork->shouldCollect([
			'onDemand'        => $this->config['requests']['on_demand'],
			'sample'          => $this->config['requests']['sample'],
			'except'          => $this->config['requests']['except'],
			'only'            => $this->config['requests']['only'],
			'exceptPreflight' => $this->config['requests']['except_preflight']
		]);

		// don't collect data for Clockwork requests
		$this->clockwork->shouldCollect()->except(rtrim($this->config['api'], '/'));
	}

	// Configure should record rules based on user configuration
	public function configureShouldRecord()
	{
		$this->clockwork->shouldRecord([
			'errorsOnly' => $this->config['requests']['errors_only'],
			'slowOnly'   => $this->config['requests']['slow_only'] ? $this->config['requests']['slow_threshold'] : false
		]);
	}

	// Set a header on PSR-7 response or using vanilla php
	protected function setHeader($header, $value)
	{
		if ($this->psrResponse) {
			$this->psrResponse = $this->psrResponse->withHeader($header, $value);
		} else {
			header("{$header}: {$value}");
		}
	}

	// Make a Clockwork incoming request instance
	protected function incomingRequest()
	{
		return new IncomingRequest([
			'method'  => $_SERVER['REQUEST_METHOD'],
			'uri'     => $_SERVER['REQUEST_URI'],
			'input'   => $_REQUEST,
			'cookies' => $_COOKIE
		]);
	}

	// Return the underlaying Clockwork instance
	public function getClockwork()
	{
		return $this->clockwork;
	}

	// Pass any method calls to the underlaying Clockwork instance
	public function __call($method, $args = [])
	{
		return $this->clockwork->$method(...$args);
	}

	// Pass any static method calls to the underlaying Clockwork instance
	public static function __callStatic($method, $args = [])
	{
		return static::instance()->$method(...$args);
	}
}
