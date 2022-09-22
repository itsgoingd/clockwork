<?php namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\Authentication\NullAuthenticator;
use Clockwork\Authentication\SimpleAuthenticator;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Helpers\StackFilter;
use Clockwork\Helpers\StackTrace;
use Clockwork\Request\IncomingRequest;
use Clockwork\Request\Request;
use Clockwork\Storage\FileStorage;
use Clockwork\Storage\Search;
use Clockwork\Storage\SqlStorage;
use Clockwork\Web\Web;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Redis\RedisManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Support class for the Laravel integration
class ClockworkSupport
{
	// Laravel application instance
	protected $app;

	// Laravel artisan (console application) instance
	protected $artisan;

	// Incoming request instance
	protected $incomingRequest;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	// Get a value form the Clockwork config
	public function getConfig($key, $default = null)
	{
		return $this->app['config']->get("clockwork.{$key}", $default);
	}

	// Retrieve metadata
	public function getData($id = null, $direction = null, $count = null, $filter = [], $extended = false)
	{
		if (isset($this->app['session'])) $this->app['session.store']->reflash();

		$authenticator = $this->app['clockwork']->authenticator();
		$storage = $this->app['clockwork']->storage();

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
			$this->addDataSources();
			$this->app['clockwork']->extendRequest($data);
		}

		$except = isset($filter['except']) ? explode(',', $filter['except']) : [];
		$only = isset($filter['only']) ? explode(',', $filter['only']) : null;

		if (is_array($data)) {
			$data = array_map(function ($request) use ($except, $only) {
				return $only ? $request->only(array_diff($only, [ 'updateToken' ])) : $request->except(array_merge($except, [ 'updateToken' ]));
			}, $data);
		} elseif ($data) {
			$data = $only ? $data->only(array_diff($only, [ 'updateToken' ])) : $data->except(array_merge($except, [ 'updateToken' ]));
		}

		return new JsonResponse($data);
	}

	// Retrieve extended metadata
	public function getExtendedData($id, $filter = [])
	{
		return $this->getData($id, null, null, $filter, true);
	}

	// Update metadata
	public function updateData($id, $input = [])
	{
		if (isset($this->app['session'])) $this->app['session.store']->reflash();

		if (! $this->isCollectingClientMetrics()) {
			throw new NotFoundHttpException;
		}

		$storage = $this->app['clockwork']->storage();

		$request = $storage->find($id);

		if (! $request) {
			return new JsonResponse([ 'message' => 'Request not found.' ], 404);
		}

		$token = isset($input['_token']) ? $input['_token'] : '';

		if (! $request->updateToken || ! hash_equals($request->updateToken, $token)) {
			return new JsonResponse([ 'message' => 'Invalid update token.' ], 403);
		}

		foreach ($input as $key => $value) {
			if (in_array($key, [ 'clientMetrics', 'webVitals' ])) {
				$request->$key = $value;
			}
		}

		$storage->update($request);
	}

	// Return an asset for web ui based on it's path, resolves correct mime-type and protectes from accessing files
	// outside of Clockwork public dir
	public function getWebAsset($path)
	{
		$asset = (new Web)->asset($path);

		if (! $asset) throw new NotFoundHttpException;

		return new BinaryFileResponse($asset['path'], 200, [ 'Content-Type' => $asset['mime'] ]);
	}

	// Add enabled data sources
	public function addDataSources()
	{
		$clockwork = $this->app['clockwork'];

		$clockwork
			->addDataSource(new PhpDataSource)
			->addDataSource($this->frameworkDataSource());

		if ($this->isFeatureEnabled('database')) $clockwork->addDataSource($this->app['clockwork.eloquent']);
		if ($this->isFeatureEnabled('cache')) $clockwork->addDataSource($this->app['clockwork.cache']);
		if ($this->isFeatureEnabled('redis')) $clockwork->addDataSource($this->app['clockwork.redis']);
		if ($this->isFeatureEnabled('queue')) $clockwork->addDataSource($this->app['clockwork.queue']);
		if ($this->isFeatureEnabled('events')) $clockwork->addDataSource($this->app['clockwork.events']);
		if ($this->isFeatureEnabled('notifications')) {
			$clockwork->addDataSource(
				$this->isFeatureAvailable('notifications-events')
					? $this->app['clockwork.notifications'] : $this->app['clockwork.swift']
			);
		}
		if ($this->isFeatureAvailable('xdebug')) $clockwork->addDataSource($this->app['clockwork.xdebug']);
		if ($this->isFeatureEnabled('views')) {
			$clockwork->addDataSource(
				$this->getConfig('features.views.use_twig_profiler', false)
					? $this->app['clockwork.twig'] : $this->app['clockwork.views']
			);
		}

		return $this;
	}

	// Start listening to events
	public function listenToEvents()
	{
		$this->frameworkDataSource()->listenToEvents();

		if ($this->isFeatureEnabled('cache')) $this->app['clockwork.cache']->listenToEvents();
		if ($this->isFeatureEnabled('database')) $this->app['clockwork.eloquent']->listenToEvents();
		if ($this->isFeatureEnabled('events')) $this->app['clockwork.events']->listenToEvents();
		if ($this->isFeatureEnabled('notifications')) {
			$this->isFeatureAvailable('notifications-events')
				? $this->app['clockwork.notifications']->listenToEvents() : $this->app['clockwork.swift']->listenToEvents();
		}
		if ($this->isFeatureEnabled('queue')) {
			$this->app['clockwork.queue']->listenToEvents();
			$this->app['clockwork.queue']->setCurrentRequestId($this->app['clockwork.request']->id);
		}
		if ($this->isFeatureEnabled('redis')) {
			$this->app[RedisManager::class]->enableEvents();
			$this->app['clockwork.redis']->listenToEvents();
		}
		if ($this->isFeatureEnabled('views')) {
			$this->getConfig('features.views.use_twig_profiler', false)
				? $this->app['clockwork.twig']->listenToEvents() : $this->app['clockwork.views']->listenToEvents();
		}

		if ($this->isCollectingCommands()) $this->collectCommands();
		if ($this->isCollectingQueueJobs()) $this->collectQueueJobs();

		return $this;
	}

	// Resolves the framework data source from the container
	protected function frameworkDataSource()
	{
		return $this->app['clockwork.laravel'];
	}

	public function handleArtisanEvents()
	{
		if (class_exists(\Illuminate\Console\Events\ArtisanStarting::class)) {
			$this->app['events']->listen(\Illuminate\Console\Events\ArtisanStarting::class, function ($event) {
				$this->artisan = $event->artisan;
			});
		}
	}

	public function handleOctaneEvents()
	{
		$this->app['events']->listen(\Laravel\Octane\Events\RequestReceived::class, function ($event) {
			$this->app = $event->sandbox;
			$this->incomingRequest = null;

			$this->app->forgetInstance('clockwork.request');
			$request = $this->app->make('clockwork.request')->override('requestTime', microtime(true));

			$this->app['clockwork']->reset()->request($request);
			$this->app['clockwork.laravel']->setApplication($this->app);
		});
	}

	// Make a storage instance based on the current configuration
	public function makeStorage()
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

			return new SqlStorage($database, $table, null, null, $expiration);
		} else {
			return new FileStorage(
				$this->getConfig('storage_files_path', storage_path('clockwork')),
				0700,
				$expiration,
				$this->getConfig('storage_files_compress', false)
			);
		}
	}

	// Make an authenticator instance based on the current configuration
	public function makeAuthenticator()
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

	// Set up collecting of executed artisan commands
	public function collectCommands()
	{
		$this->app['events']->listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) {
			// only collect commands ran through artisan cli, other commands are recorded as part of respective request
			if (basename(StackTrace::get()->last()->file) != 'artisan') return;

			if (! $this->getConfig('artisan.collect_output')) return;
			if (! $event->command || $this->isCommandFiltered($event->command)) return;

			$event->output->setFormatter(
				version_compare(\Illuminate\Foundation\Application::VERSION, '9.0.0', '<')
					? new Console\CapturingLegacyFormatter($event->output->getFormatter())
					: new Console\CapturingFormatter($event->output->getFormatter())
			);
		});

		$this->app['events']->listen(\Illuminate\Console\Events\CommandFinished::class, function ($event) {
			// only collect commands ran through artisan cli, other commands are recorded as part of respective request
			if (basename(StackTrace::get()->last()->file) != 'artisan') return;

			if (! $event->command || $this->isCommandFiltered($event->command)) return;

			$command = $this->artisan->find($event->command);

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

			$this->app->make('clockwork')->reset()->request($request);

			$this->app['clockwork.queue']->setCurrentRequestId($request->id);
		});

		$this->app['events']->listen(\Illuminate\Queue\Events\JobProcessed::class, function ($event) {
			$this->processQueueJob($event->job);
		});

		$this->app['events']->listen(\Illuminate\Queue\Events\JobFailed::class, function ($event) {
			$this->processQueueJob($event->job, $event->exception);
		});
	}

	// Process an executed queue job, resolves and records the current request
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

	// Process an executed http request, resolves the current request, sets Clockwork headers and cookies
	public function processRequest($request, $response)
	{
		if (! $this->isCollectingRequests()) {
			return $response; // Clockwork is not collecting data, additional check when the middleware is enabled manually
		}

		$clockwork = $this->app['clockwork'];
		$clockworkRequest = $clockwork->request();

		$clockwork->event('Controller')->end();

		$this->setResponse($response);

		$clockwork->resolveRequest();

		if (! $this->isEnabled() || ! $this->isRecording($clockworkRequest)) {
			return $response; // Clockwork is disabled or we are not recording this request
		}

		$response->headers->set('X-Clockwork-Id', $clockworkRequest->id, true);
		$response->headers->set('X-Clockwork-Version', Clockwork::VERSION, true);

		if ($request->getBasePath()) {
			$response->headers->set('X-Clockwork-Path', $request->getBasePath() . '/__clockwork/', true);
		}

		foreach ($this->getConfig('headers', []) as $headerName => $headerValue) {
			$response->headers->set("X-Clockwork-Header-{$headerName}", $headerValue);
		}

		foreach ($clockwork->request()->subrequests as $subrequest) {
			$url = urlencode($subrequest['url']);
			$path = urlencode($subrequest['path']);

			$response->headers->set('X-Clockwork-Subrequest', "{$subrequest['id']};{$url};{$path}", false);
		}

		$this->appendServerTimingHeader($response, $clockworkRequest);

		if (! ($response instanceof Response)) {
			return $response;
		}

		if ($this->isCollectingClientMetrics() || $this->isToolbarEnabled()) {
			$clockworkBrowser = [
				'requestId' => $clockworkRequest->id,
				'version'   => Clockwork::VERSION,
				'path'      => $request->getBasePath() . '/__clockwork/',
				'webPath'   => $request->getBasePath() . '/' . $this->webPaths()[0] . '/app',
				'token'     => $clockworkRequest->updateToken,
				'metrics'   => $this->isCollectingClientMetrics(),
				'toolbar'   => $this->isToolbarEnabled()
			];

			$response->cookie(
				new Cookie('x-clockwork', json_encode($clockworkBrowser), time() + 60, null, null, null, false)
			);
		}

		return $response;
	}

	// Records the current http request
	public function recordRequest()
	{
		if (! $this->isCollectingRequests()) {
			return; // Clockwork is not collecting data, additional check when the middleware is enabled manually
		}

		$clockwork = $this->app['clockwork'];

		if (! $this->isRecording($clockwork->request())) {
			return; // Collecting data is disabled, return immediately
		}

		$clockwork->storeRequest();
	}

	// Set current http response on the framework data source
	protected function setResponse($response)
	{
		$this->app['clockwork.laravel']->setResponse($response);
	}

	// Configure serializer defaults
	public function configureSerializer()
	{
		Serializer::defaults([
			'limit'       => $this->getConfig('serialization_depth'),
			'blackbox'    => $this->getConfig('serialization_blackbox'),
			'traces'      => $this->getConfig('stack_traces.enabled', true),
			'tracesSkip'  => StackFilter::make()
				->isNotVendor(array_merge(
					$this->getConfig('stack_traces.skip_vendors', []),
					[ 'itsgoingd', 'laravel', 'illuminate', 'psr' ]
				))
				->isNotNamespace($this->getConfig('stack_traces.skip_namespaces', []))
				->isNotFunction([ 'call_user_func', 'call_user_func_array' ])
				->isNotClass($this->getConfig('stack_traces.skip_classes', [])),
			'tracesLimit' => $this->getConfig('stack_traces.limit', 10)
		]);

		return $this;
	}

	// Configure should collect rules
	public function configureShouldCollect()
	{
		$this->app['clockwork']->shouldCollect([
			'onDemand'        => $this->getConfig('requests.on_demand', false),
			'sample'          => $this->getConfig('requests.sample', false),
			'except'          => $this->getConfig('requests.except', []),
			'only'            => $this->getConfig('requests.only', []),
			'exceptPreflight' => $this->getConfig('requests.except_preflight', [])
		]);

		// don't collect data for Clockwork requests
		$webPath = $this->webPaths()[0];
		$this->app['clockwork']->shouldCollect()->except([ '/__clockwork(?:/.*)?', "/{$webPath}(?:/.*)?" ]);

		return $this;
	}

	// Configure should record rules
	public function configureShouldRecord()
	{
		$this->app['clockwork']->shouldRecord([
			'errorsOnly' => $this->getConfig('requests.errors_only', false),
			'slowOnly'   => $this->getConfig('requests.slow_only', false) ? $this->getConfig('requests.slow_threshold') : false
		]);

		return $this;
	}

	// Check whether Clockwork is enabled at all
	public function isEnabled()
	{
		return $this->getConfig('enable')
			|| $this->getConfig('enable') === null && $this->app['config']->get('app.debug');
	}

	// Check whether we are collecting data
	public function isCollectingData()
	{
		return $this->isCollectingCommands()
			|| $this->isCollectingQueueJobs()
			|| $this->isCollectingRequests()
			|| $this->isCollectingTests();
	}

	// Check whether we are collecting artisan commands
	public function isCollectingCommands()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& $this->app->runningInConsole()
			&& $this->getConfig('artisan.collect', false);
	}

	// Check whether we are collecting queue jobs
	public function isCollectingQueueJobs()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& $this->app->runningInConsole()
			&& $this->getConfig('queue.collect', false);
	}

	// Check whether we are collecting http requests
	public function isCollectingRequests()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& ! $this->app->runningInConsole()
			&& $this->app['clockwork']->shouldCollect()->filter($this->incomingRequest());
	}

	// Check whether we are collecting tests
	public function isCollectingTests()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& $this->app->runningInConsole()
			&& $this->getConfig('tests.collect', false);
	}

	// Check whether we are recording the passed request
	public function isRecording($incomingRequest)
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& $this->app['clockwork']->shouldRecord()->filter($incomingRequest);
	}

	// Check whether a feature is enabled
	public function isFeatureEnabled($feature)
	{
		return $this->getConfig("features.{$feature}.enabled") && $this->isFeatureAvailable($feature);
	}

	// Check whether a feature is available
	public function isFeatureAvailable($feature)
	{
		if ($feature == 'database') {
			return $this->app['config']->get('database.default');
		} elseif ($feature == 'notifications-events') {
			return class_exists(\Illuminate\Mail\Events\MessageSent::class)
				&& class_exists(\Illuminate\Notifications\Events\NotificationSent::class);
		} elseif ($feature == 'redis') {
			return method_exists(\Illuminate\Redis\RedisManager::class, 'enableEvents');
		} elseif ($feature == 'queue') {
			return method_exists(\Illuminate\Queue\Queue::class, 'createPayloadUsing');
		} elseif ($feature == 'xdebug') {
			return in_array('xdebug', get_loaded_extensions());
		}

		return true;
	}

	// Check whether we are collecting client metrics
	public function isCollectingClientMetrics()
	{
		return $this->getConfig('features.performance.client_metrics', true);
	}

	// Check whether the toolbar is enabled
	public function isToolbarEnabled()
	{
		return $this->getConfig('toolbar', false);
	}

	// Check whether the web ui is enabled
	public function isWebEnabled()
	{
		return $this->getConfig('web', true);
	}

	// Check whether a command should not be collected
	protected function isCommandFiltered($command)
	{
		$only = $this->getConfig('artisan.only', []);

		if (count($only)) return ! in_array($command, $only);

		$except = $this->getConfig('artisan.except', []);

		if ($this->getConfig('artisan.except_laravel_commands', true)) {
			$except = array_merge($except, $this->builtinLaravelCommands());
		}

		$except = array_merge($except, $this->builtinClockworkCommands());

		return in_array($command, $except);
	}

	// Check whether a queue job should not be collected
	protected function isQueueJobFiltered($queueJob)
	{
		$only = $this->getConfig('queue.only', []);

		if (count($only)) return ! in_array($queueJob, $only);

		$except = $this->getConfig('queue.except', []);

		return in_array($queueJob, $except);
	}

	// Check whether a test should not be collected
	public function isTestFiltered($test)
	{
		$except = $this->getConfig('tests.except', []);

		return in_array($test, $except);
	}

	// Append server timing headers from a Clockwork request to a http response
	protected function appendServerTimingHeader($response, $request)
	{
		if (($eventsCount = $this->getConfig('server_timing', 10)) !== false) {
			$response->headers->set('Server-Timing', ServerTiming::fromRequest($request, $eventsCount)->value());
		}
	}

	// Make an incoming request instance
	protected function incomingRequest()
	{
		if ($this->incomingRequest) return $this->incomingRequest;

		return $this->incomingRequest = new IncomingRequest([
			'method'  => $this->app['request']->getMethod(),
			'uri'     => $this->app['request']->getRequestUri(),
			'input'   => $this->app['request']->input(),
			'cookies' => $this->app['request']->cookie()
		]);
	}

	// Return an array of web ui paths
	public function webPaths()
	{
		$path = $this->getConfig('web', true);

		if (is_string($path)) return collect([ trim($path, '/') ]);

		return collect([ 'clockwork', '__clockwork' ]);
	}

	// Return an array of built-in Laravel commands
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
			'octane:install', 'octane:reload', 'octane:start', 'octane:status', 'octane:stop',
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

	// Return an array of built-in Clockwork commands
	protected function builtinClockworkCommands()
	{
		return [
			'clockwork:clean'
		];
	}
}
