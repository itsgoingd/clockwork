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

		$clockworkRequest = $this->request();

		$this->setHeader('X-Clockwork-Id', $clockworkRequest->id);
		$this->setHeader('X-Clockwork-Version', BaseClockwork::VERSION);

		if ($this->config['api'] != '/__clockwork/') {
			$this->setHeader('X-Clockwork-Path', $this->config['api']);
		}

		foreach ($this->config['headers'] as $headerName => $headerValue) {
			$this->setHeader("X-Clockwork-Header-{$headerName}", $headerValue);
		}

		if ($this->config['features']['performance']['client_metrics'] || $this->config['toolbar']) {
			$this->setCookie('x-clockwork', $this->getCookiePayload(), time() + 60);
		}
	}

	// Returns the x-clockwork cookie payload in case you need to set the cookie yourself (cookie can't be http only,
	// expiration time should be 60 seconds)
	public function getCookiePayload()
	{
		$clockworkRequest = $this->request();

		return json_encode([
			'requestId' => $clockworkRequest->id,
			'version'   => BaseClockwork::VERSION,
			'path'      => $this->config['api'],
			'webPath'   => $this->config['web']['enable'],
			'token'     => $clockworkRequest->updateToken,
			'metrics'   => $this->config['features']['performance']['client_metrics'],
			'toolbar'   => $this->config['toolbar']
		]);
	}

	// Handle Clockwork REST api request, retrieves or updates Clockwork metadata
	public function handleMetadata($request = null, $method = null)
	{
		if (! $method) $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

		return $method == 'POST' ? $this->updateMetadata($request) : $this->returnMetadata($request);
	}

	// Retrieve metadata based on the passed Clockwork REST api request and send HTTP response
	public function returnMetadata($request = null)
	{
		if (! $this->config['enable']) return;

		return $this->response($this->getMetadata($request));
	}

	// Returns metadata based on the passed Clockwork REST api request
	public function getMetadata($request = null)
	{
		if (! $this->config['enable']) return $this->response(null, 404);

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

	// Update metadata based on the passed Clockwork REST api request and send HTTP response
	public function updateMetadata($request = null)
	{
		if (! $this->config['enable'] || ! $this->config['features']['performance']['client_metrics']) {
			return $this->response(null, 404);
		}

		if (! $request) $request = isset($_GET['request']) ? $_GET['request'] : '';

		$storage = $this->clockwork->storage();

		$request = $storage->find($request);

		if (! $request) {
			return $this->response([ 'message' => 'Request not found.' ], 404);
		}

		$input = json_decode(file_get_contents('php://input'), true);

		$token = isset($input['_token']) ? $input['_token'] : '';

		if (! $request->updateToken || ! hash_equals($request->updateToken, $token)) {
			return $this->response([ 'message' => 'Invalid update token.' ], 403);
		}

		foreach ($input as $key => $value) {
			if (in_array($key, [ 'clientMetrics', 'webVitals' ])) {
				$request->$key = $value;
			}
		}

		$storage->update($request);

		return $this->response();
	}

	// Returns the Clockwork Web UI as a HTTP response, installs the Web UI on the first run
	public function returnWeb()
	{
		if (! $this->config['web']['enable']) return;

		$this->installWeb();

		$asset = function ($uri) { return "{$this->config['web']['uri']}/{$uri}"; };
		$metadataPath = $this->config['api'];
		$url = $this->config['web']['uri'];

		if (! preg_match('#/index.html$#', $url)) {
			$url = rtrim($url, '/') . '/index.html';
		}

		ob_start();

		include __DIR__ . '/iframe.html.php';

		$html = ob_get_clean();

		return $this->response($html, null, false);
	}

	// Installs the Web UI by copying the assets to the public directory, no-op if already installed
	public function installWeb()
	{
		$path = $this->config['web']['path'];
		$source = __DIR__ . '/../../Web/public';

		if (file_exists("{$path}/index.html")) return;

		@mkdir($path, 0755, true);

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $item) {
			if ($item->isDir()) {
				mkdir("{$path}/" . $iterator->getSubPathName());
			} else {
				copy($item, "{$path}/" . $iterator->getSubPathName());
			}
		}
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
		$this->clockwork->shouldCollect()->except(preg_quote(rtrim($this->config['api'], '/'), '#'));
	}

	// Configure should record rules based on user configuration
	public function configureShouldRecord()
	{
		$this->clockwork->shouldRecord([
			'errorsOnly' => $this->config['requests']['errors_only'],
			'slowOnly'   => $this->config['requests']['slow_only'] ? $this->config['requests']['slow_threshold'] : false
		]);
	}

	// Set a cookie on PSR-7 response or using vanilla php
	protected function setCookie($name, $value, $expires) {
		if ($this->psrResponse) {
			$this->psrResponse = $this->psrResponse->withAddedHeader(
				'Set-Cookie', "{$name}=" . urlencode($value) . '; expires=' . gmdate('D, d M Y H:i:s T', $expires)
			);
		} else {
			setcookie($name, $value, $expires);
		}
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

	// Send a json response, uses the PSR-7 response if set
	protected function response($data = null, $status = null, $json = true)
	{
		if ($json) $this->setHeader('Content-Type', 'application/json');

		if ($this->psrResponse) {
			if ($status) $this->psrResponse = $this->psrResponse->withStatus($status);
			$this->psrResponse->getBody()->write($json ? json_encode($data, \JSON_PARTIAL_OUTPUT_ON_ERROR) : $data);
			return $this->psrResponse;
		} else {
			if ($status) http_response_code($status);
			echo $json ? json_encode($data, \JSON_PARTIAL_OUTPUT_ON_ERROR) : $data;
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

	// Return the underlying Clockwork instance
	public function getClockwork()
	{
		return $this->clockwork;
	}

	// Pass any method calls to the underlying Clockwork instance
	public function __call($method, $args = [])
	{
		return $this->clockwork->$method(...$args);
	}

	// Pass any static method calls to the underlying Clockwork instance
	public static function __callStatic($method, $args = [])
	{
		return static::instance()->$method(...$args);
	}
}
