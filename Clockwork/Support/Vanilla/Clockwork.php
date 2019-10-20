<?php namespace Clockwork\Support\Vanilla;

use Clockwork\Clockwork as BaseClockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\PsrMessageDataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Helpers\StackFilter;
use Clockwork\Helpers\StackTrace;
use Clockwork\Storage\FileStorage;
use Clockwork\Storage\Search;
use Clockwork\Storage\SqlStorage;

use Psr\Http\Message\ServerRequestInterface as PsrRequest;
use Psr\Http\Message\ResponseInterface as PsrResponse;

class Clockwork
{
	protected $config;
	protected $clockwork;

	protected $psrRequest;
	protected $psrResponse;

	protected static $defaultInstance;

	public function __construct($config = [])
	{
		$this->config = array_merge(include __DIR__ . '/config.php', $config);

		$this->clockwork = new BaseClockwork;

		$this->clockwork->addDataSource(new PhpDataSource);
		$this->clockwork->setStorage($this->resolveStorage());

		$this->configureSerializer();

		if ($this->config['register_helpers']) include __DIR__ . '/helpers.php';

		$this->clockwork->getTimeline()->startEvent('total', 'Total execution time.', 'start');
	}

	public static function init($config = [])
	{
		return static::$defaultInstance = new static($config);
	}

	public static function instance()
	{
		return static::$defaultInstance;
	}

	public function requestProcessed()
	{
		if (! $this->config['enable'] && ! $this->config['collect_data_always']) return;

		$this->clockwork->getTimeline()->endEvent('total');

		$this->clockwork->resolveRequest()->storeRequest();

		if (! $this->config['enable']) return;

		$this->setHeader('X-Clockwork-Id', $this->getRequest()->id);
		$this->setHeader('X-Clockwork-Version', BaseClockwork::VERSION);

		if ($this->config['api'] != '/__clockwork/') {
			$this->setHeader('X-Clockwork-Path', $this->config['api']);
		}

		foreach ($this->config['headers'] as $headerName => $headerValue) {
			$this->setHeader("X-Clockwork-Header-{$headerName}", $headerValue);
		}

		if (($eventsCount = $this->config['server_timing']) !== false) {
			$this->setHeader('Server-Timing', ServerTiming::fromRequest($this->clockwork->getRequest(), $eventsCount)->value());
		}

		return $this->psrResponse;
	}

	public function returnMetadata($request = null)
	{
		if (! $this->config['enable']) return;

		$this->setHeader('Content-Type', 'application/json');

		echo json_encode($this->getMetadata($request), \JSON_PARTIAL_OUTPUT_ON_ERROR);
	}

	public function getMetadata($request = null)
	{
		if (! $this->config['enable']) return;

		if (! $request) $request = isset($_GET['request']) ? $_GET['request'] : '';

		preg_match('#(?<id>[0-9-]+|latest)(?:/(?<direction>next|previous))?(?:/(?<count>\d+))?#', $request, $matches);

		$id = isset($matches['id']) ? $matches['id'] : null;
		$direction = isset($matches['direction']) ? $matches['direction'] : null;
		$count = isset($matches['count']) ? $matches['count'] : null;

		if ($direction == 'previous') {
			$data = $this->clockwork->getStorage()->previous($id, $count, Search::fromRequest($_GET));
		} elseif ($direction == 'next') {
			$data = $this->clockwork->getStorage()->next($id, $count, Search::fromRequest($_GET));
		} elseif ($id == 'latest') {
			$data = $this->clockwork->getStorage()->latest(Search::fromRequest($_GET));
		} else {
			$data = $this->clockwork->getStorage()->find($id);
		}

		if (preg_match('#(?<id>[0-9-]+|latest)/extended#', $request)) {
			$this->clockwork->extendRequest($data);
		}

		if ($data) {
			$data = is_array($data) ? array_map(function ($item) { return $item->toArray(); }, $data) : $data->toArray();
		}

		return $data;
	}

	public function usePsrMessage(PsrRequest $request, PsrResponse $response = null)
	{
		$this->psrRequest = $request;
		$this->psrResponse = $response;

		$this->clockwork->addDataSource(new PsrMessageDataSource($request, $response));

		return $this;
	}

	protected function resolveStorage()
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

	protected function setHeader($header, $value)
	{
		if ($this->psrResponse) {
			$this->psrResponse = $this->psrResponse->withHeader($header, $value);
		} else {
			header("{$header}: {$value}");
		}
	}

	public function getClockwork()
	{
		return $this->clockwork;
	}

	public function __call($method, $args = [])
	{
		return call_user_func_array([ $this->getClockwork(), $method ], $args);
	}

	public static function __callStatic($method, $args = [])
	{
		return call_user_func_array([ static::instance(), $method ], $args);
	}
}
