<?php namespace Clockwork\Support\Vanilla;

use Clockwork\Clockwork as BaseClockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Storage\FileStorage;

class Clockwork
{
	protected $config;
	protected $clockwork;

	protected static $defaultInstance;

	public function __construct($config = [])
	{
		$this->config = array_merge(include __DIR__ . '/config.php', $config);

		$this->clockwork = new BaseClockwork;

		$this->clockwork->addDataSource(new PhpDataSource);
		$this->clockwork->setStorage($this->resolveStorage());

		$this->clockwork->getLog()->collectStackTraces($this->config['collect_stack_traces']);

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

		header('X-Clockwork-Id: ' . $this->getRequest()->id);
		header('X-Clockwork-Version: ' . BaseClockwork::VERSION);

		if ($this->config['api_uri'] != '/__clockwork/') {
			header('X-Clockwork-Path: ' . $this->config['api_uri']);
		}

		foreach ($this->config['headers'] as $headerName => $headerValue) {
			header("X-Clockwork-Header-{$headerName}: {$headerValue}");
		}

		if (($eventsCount = $this->config['server_timing']) !== false) {
			header('Server-Timing: ' . ServerTiming::fromRequest($this->clockwork->getRequest(), $eventsCount)->value());
		}
	}

	public function returnMetadata($request = null)
	{
		if (! $this->config['enable']) return;

		header('Content-Type: application/json');

		echo $this->getStorage()->find($request ?: $_GET['request'])->toJson();
	}

	public function resolveStorage()
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
				$this->config['storage_files_path'], 0700, $this->config['storage_expiration']
			);
		}

		$storage->filter = $this->config['filter'];

		return $storage;
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