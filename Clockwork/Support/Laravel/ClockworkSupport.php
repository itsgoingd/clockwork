<?php namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\Authentication\NullAuthenticator;
use Clockwork\Authentication\SimpleAuthenticator;
use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Helpers\StackFilter;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\Request;
use Clockwork\Storage\FileStorage;
use Clockwork\Storage\Search;
use Clockwork\Storage\SqlStorage;
use Clockwork\Web\Web;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClockworkSupport
{
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function getConfig($key, $default = null)
	{
		return $this->app['config']->get("clockwork.{$key}", $default);
	}

	public function getData($id = null, $direction = null, $count = null, $extended = false)
	{
		if (isset($this->app['session'])) $this->app['session.store']->reflash();

		$authenticator = $this->app['clockwork']->getAuthenticator();
		$storage = $this->app['clockwork']->getStorage();

		$authenticated = $authenticator->check($this->app['request']->header('X-Clockwork-Auth'));

		if ($authenticated !== true) {
			return new JsonResponse([ 'message' => $authenticated, 'requires' => $authenticator->requires() ], 403);
		}

		if ($direction == 'previous') {
			$data = $storage->previous($id, $count, Search::fromRequest($this->app['request']->all()));
		} elseif ($direction == 'next') {
			$data = $storage->next($id, $count, Search::fromRequest($this->app['request']->all()));
		} elseif ($id == 'latest') {
			$data = $storage->latest(Search::fromRequest($this->app['request']->all()));
		} else {
			$data = $storage->find($id);
		}

		if ($extended) {
			$this->app['clockwork']->extendRequest($data);
		}

		return new JsonResponse($data);
	}

	public function getExtendedData($id)
	{
		return $this->getData($id, null, null, true);
	}

	public function getStorage()
	{
		$expiration = $this->getConfig('storage_expiration');

		if ($this->getConfig('storage', 'files') == 'sql') {
			$database = $this->getConfig('storage_sql_database', storage_path('clockwork.sqlite'));
			$table = $this->getConfig('storage_sql_table', 'clockwork');

			if ($this->app['config']->get("database.connections.{$database}")) {
				$database = $this->app['db']->connection($database)->getPdo();
			} else {
				$database = "sqlite:{$database}";
			}

			$storage = new SqlStorage($database, $table, null, null, $expiration);
		} else {
			$storage = new FileStorage(
				$this->getConfig('storage_files_path', storage_path('clockwork')),
				0700,
				$expiration,
				$this->getConfig('storage_files_compress', false)
			);
		}

		return $storage;
	}

	public function getAuthenticator()
	{
		$authenticator = $this->getConfig('authentication');

		if (is_string($authenticator)) {
			return $this->app->make($authenticator);
		} elseif ($authenticator) {
			return new SimpleAuthenticator($this->getConfig('authentication_password'));
		} else {
			return new NullAuthenticator;
		}
	}

	public function getWebAsset($path)
	{
		$web = new Web;

		if ($asset = $web->asset($path)) {
			return new BinaryFileResponse($asset['path'], 200, [ 'Content-Type' => $asset['mime'] ]);
		} else {
			throw new NotFoundHttpException;
		}
	}

	// Set up collecting of executed artisan commands
	public function collectCommands()
	{
		$this->app['events']->listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) {
			// only collect commands ran through artisan cli, other commands are recorded as part of respective request
			if (basename(StackTrace::get()->last()->file) != 'artisan') return;

			if (! $this->getConfig('artisan.collect_output')) return;
			if (! $event->command || $this->isCommandFiltered($event->command)) return;

			$event->output->setFormatter(
				new Console\CapturingFormatter($event->output->getFormatter())
			);
		});

		$this->app['events']->listen(\Illuminate\Console\Events\CommandFinished::class, function ($event) {
			// only collect commands ran through artisan cli, other commands are recorded as part of respective request
			if (basename(StackTrace::get()->last()->file) != 'artisan') return;

			if (! $event->command || $this->isCommandFiltered($event->command)) return;

			$command = $this->app->make(ConsoleKernel::class)->all()[$event->command];

			$allArguments = $event->input->getArguments();
			$allOptions = $event->input->getOptions();

			$defaultArguments = $command->getDefinition()->getArgumentDefaults();
			$defaultOptions = $command->getDefinition()->getOptionDefaults();

			$this->app->make('clockwork')
				->resolveAsCommand(
					$event->command,
					$event->exitCode,
					array_udiff_assoc($allArguments, $defaultArguments, function ($a, $b) { return $a == $b ? 0 : 1; }),
					array_udiff_assoc($allOptions, $defaultOptions, function ($a, $b) { return $a == $b ? 0 : 1; }),
					$defaultArguments,
					$defaultOptions,
					$this->getConfig('artisan.collect_output') ? $event->output->getFormatter()->capturedOutput() : null
				)
				->storeRequest();
		});
	}

	// Set up collecting of executed queue jobs
	public function collectQueueJobs()
	{
		$this->app['events']->listen(\Illuminate\Queue\Events\JobProcessing::class, function ($event) {
			// sync jobs are recorded as part of the parent request
			if ($event->job instanceof \Illuminate\Queue\Jobs\SyncJob) return;

			$payload = $event->job->payload();

			if (! isset($payload['clockwork_id']) || $this->isQueueJobFiltered($payload['displayName'])) return;

			$request = new Request([ 'id' => $payload['clockwork_id'] ]);
			if (isset($payload['clockwork_parent_id'])) $request->setParent($payload['clockwork_parent_id']);

			$this->app->make('clockwork')->reset()->setRequest($request)
				->startEvent('total', 'Total execution time.', $request->time);
		});

		$this->app['events']->listen(\Illuminate\Queue\Events\JobProcessed::class, function ($event) {
			$this->processQueueJob($event->job);
		});

		$this->app['events']->listen(\Illuminate\Queue\Events\JobFailed::class, function ($event) {
			$this->processQueueJob($event->job, $event->exception);
		});
	}

	protected function processQueueJob($job, $exception = null)
	{
		// sync jobs are recorded as part of the parent request
		if ($job instanceof \Illuminate\Queue\Jobs\SyncJob) return;

		$payload = $job->payload();

		if (! isset($payload['clockwork_id'])) return;

		$unserialized = isset($payload['data']['command']) ? unserialize($payload['data']['command']) : null;

		if (! $unserialized || $this->isQueueJobFiltered(get_class($unserialized))) return;

		if ($exception) {
			$this->app->make('clockwork')->error($exception->getMessage(), [ 'exception' => $exception ]);
		}

		$this->app->make('clockwork')
			->resolveAsQueueJob(
				get_class($unserialized),
				$payload['displayName'],
				$job->hasFailed() ? 'failed' : ($job->isReleased() ? 'released' : 'done'),
				$unserialized,
				$job->getQueue(),
				$job->getConnectionName(),
				array_filter([
					'maxTries'     => isset($payload['maxTries']) ? $payload['maxTries'] : null,
					'delaySeconds' => isset($payload['delaySeconds']) ? $payload['delaySeconds'] : null,
					'timeout'      => isset($payload['timeout']) ? $payload['timeout'] : null,
					'timeoutAt'    => isset($payload['timeoutAt']) ? $payload['timeoutAt'] : null
				])
			)
			->storeRequest();
	}

	public function process($request, $response)
	{
		if (! $this->isCollectingRequests()) {
			return $response; // Collecting data is disabled, return immediately
		}

		$this->setResponse($response);

		$this->app['clockwork']->resolveRequest();
		$this->app['clockwork']->storeRequest();

		if (! $this->isEnabled()) {
			return $response; // Clockwork is disabled, don't set the headers
		}

		$response->headers->set('X-Clockwork-Id', $this->app['clockwork']->getRequest()->id, true);
		$response->headers->set('X-Clockwork-Version', Clockwork::VERSION, true);

		if ($request->getBasePath()) {
			$response->headers->set('X-Clockwork-Path', $request->getBasePath() . '/__clockwork/', true);
		}

		foreach ($this->getConfig('headers', []) as $headerName => $headerValue) {
			$response->headers->set("X-Clockwork-Header-{$headerName}", $headerValue);
		}

		foreach ($this->app['clockwork']->getRequest()->subrequests as $subrequest) {
			$url = urlencode($subrequest['url']);
			$path = urlencode($subrequest['path']);

			$response->headers->set('X-Clockwork-Subrequest', "{$subrequest['id']};{$url};{$path}", false);
		}

		$this->appendServerTimingHeader($response, $this->app['clockwork']->getRequest());

		return $response;
	}

	protected function setResponse($response)
	{
		$this->app['clockwork.laravel']->setResponse($response);
	}

	public function configureSerializer()
	{
		Serializer::defaults([
			'limit'       => $this->getConfig('serialization_depth'),
			'blackbox'    => $this->getConfig('serialization_blackbox'),
			'traces'      => $this->getConfig('stack_traces.enabled', true),
			'tracesSkip'  => StackFilter::make()
				->isNotVendor(array_merge(
					$this->getConfig('stack_traces.skip_vendors', []),
					[ 'itsgoingd', 'laravel', 'illuminate' ]
				))
				->isNotNamespace($this->getConfig('stack_traces.skip_namespaces', []))
				->isNotFunction([ 'call_user_func', 'call_user_func_array' ])
				->isNotClass($this->getConfig('stack_traces.skip_classes', [])),
			'tracesLimit' => $this->getConfig('stack_traces.limit', 10)
		]);
	}

	public function isEnabled()
	{
		return $this->getConfig('enable')
			|| $this->getConfig('enable') === null && $this->app['config']->get('app.debug');
	}

	public function isCollectingData()
	{
		return $this->isCollectingCommands()
			|| $this->isCollectingQueueJobs()
			|| $this->isCollectingRequests()
			|| $this->isCollectingTests();
	}

	public function isCollectingCommands()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& $this->app->runningInConsole()
			&& $this->getConfig('artisan.collect', false);
	}

	public function isCollectingQueueJobs()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& $this->app->runningInConsole()
			&& $this->getConfig('queue.collect', false);
	}

	public function isCollectingRequests()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& ! $this->app->runningInConsole()
			&& ! $this->isMethodFiltered($this->app['request']->getMethod())
			&& ! $this->isUriFiltered($this->app['request']->getRequestUri());
	}

	public function isCollectingTests()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& $this->app->runningInConsole()
			&& $this->getConfig('tests.collect', false);
	}

	public function isFeatureEnabled($feature)
	{
		return $this->getConfig("features.{$feature}.enabled") && $this->isFeatureAvailable($feature);
	}

	public function isFeatureAvailable($feature)
	{
		if ($feature == 'database') {
			return $this->app['config']->get('database.default');
		} elseif ($feature == 'redis') {
			return method_exists(\Illuminate\Redis\RedisManager::class, 'enableEvents');
		} elseif ($feature == 'queue') {
			return method_exists(\Illuminate\Queue\Queue::class, 'createPayloadUsing');
		} elseif ($feature == 'xdebug') {
			return in_array('xdebug', get_loaded_extensions());
		}

		return true;
	}

	public function isWebEnabled()
	{
		return $this->getConfig('web', true);
	}

	public function isUriFiltered($uri)
	{
		$filterUris = $this->getConfig('filter_uris', []);
		$filterUris[] = '/__clockwork(?:/.*)?'; // don't collect data for Clockwork requests

		foreach ($filterUris as $filterUri) {
			$regexp = '#' . str_replace('#', '\#', $filterUri) . '#';

			if (preg_match($regexp, $uri)) return true;
		}

		return false;
	}

	public function isMethodFiltered($method)
	{
		return in_array($method, array_map(
			function ($method) { return strtoupper($method); },
			$this->getConfig('filter_methods', [])
		));
	}

	protected function isCommandFiltered($command)
	{
		$whitelist = $this->getConfig('artisan.only', []);

		if (count($whitelist)) return ! in_array($command, $whitelist);

		$blacklist = $this->getConfig('artisan.except', []);

		if ($this->getConfig('artisan.except_laravel_commands', true)) {
			$blacklist = array_merge($blacklist, $this->builtinLaravelCommands());
		}

		$blacklist = array_merge($blacklist, $this->builtinClockworkCommands());

		return in_array($command, $blacklist);
	}

	protected function isQueueJobFiltered($queueJob)
	{
		$whitelist = $this->getConfig('queue.only', []);

		if (count($whitelist)) return ! in_array($queueJob, $whitelist);

		$blacklist = $this->getConfig('queue.except', []);

		return in_array($queueJob, $blacklist);
	}

	public function isTestFiltered($test)
	{
		$blacklist = $this->getConfig('tests.except', []);

		return in_array($test, $blacklist);
	}

	protected function appendServerTimingHeader($response, $request)
	{
		if (($eventsCount = $this->getConfig('server_timing', 10)) !== false) {
			$response->headers->set('Server-Timing', ServerTiming::fromRequest($request, $eventsCount)->value());
		}
	}

	protected function builtinLaravelCommands()
	{
		return [
			'clear-compiled', 'down', 'dump-server', 'env', 'help', 'list', 'migrate', 'optimize', 'preset', 'serve',
			'tinker', 'up',
			'app:name',
			'auth:clear-resets',
			'cache:clear', 'cache:forget', 'cache:table',
			'config:cache', 'config:clear',
			'db:seed',
			'event:cache', 'event:clear', 'event:generate', 'event:list',
			'key:generate',
			'make:auth', 'make:channel', 'make:command', 'make:controller', 'make:event', 'make:exception',
			'make:factory', 'make:job', 'make:listener', 'make:mail', 'make:middleware', 'make:migration', 'make:model',
			'make:notification', 'make:observer', 'make:policy', 'make:provider', 'make:request', 'make:resource',
			'make:rule', 'make:seeder', 'make:test',
			'migrate:fresh', 'migrate:install', 'migrate:refresh', 'migrate:reset', 'migrate:rollback',
			'migrate:status',
			'notifications:table',
			'optimize:clear',
			'package:discover',
			'queue:failed', 'queue:failed-table', 'queue:flush', 'queue:forget', 'queue:listen', 'queue:restart',
			'queue:retry', 'queue:table', 'queue:work',
			'route:cache', 'route:clear', 'route:list',
			'schedule:run',
			'session:table',
			'storage:link',
			'vendor:publish',
			'view:cache', 'view:clear'
		];
	}

	protected function builtinClockworkCommands()
	{
		return [
			'clockwork:clean'
		];
	}
}
