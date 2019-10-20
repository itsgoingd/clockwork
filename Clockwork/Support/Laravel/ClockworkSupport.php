<?php namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\Authentication\NullAuthenticator;
use Clockwork\Authentication\SimpleAuthenticator;
use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Helpers\StackFilter;
use Clockwork\Helpers\StackTrace;
use Clockwork\Storage\FileStorage;
use Clockwork\Storage\Search;
use Clockwork\Storage\SqlStorage;
use Clockwork\Web\Web;

use Illuminate\Contracts\Foundation\Application;
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

	public function process($request, $response)
	{
		if (! $this->isCollectingData()) {
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
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& ! $this->app->runningInConsole()
			&& ! $this->isUriFiltered($this->app['request']->getRequestUri());
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

	public function isWebUsingDarkTheme()
	{
		return $this->getConfig('web_dark_theme', false);
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

	protected function appendServerTimingHeader($response, $request)
	{
		if (($eventsCount = $this->getConfig('server_timing', 10)) !== false) {
			$response->headers->set('Server-Timing', ServerTiming::fromRequest($request, $eventsCount)->value());
		}
	}
}
