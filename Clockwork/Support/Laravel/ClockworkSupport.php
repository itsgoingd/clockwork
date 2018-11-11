<?php namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\Authentication\NullAuthenticator;
use Clockwork\Authentication\SimpleAuthenticator;
use Clockwork\Helpers\Serializer;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Storage\FileStorage;
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
		$this->app['session.store']->reflash();

		$authenticator = $this->app['clockwork']->getAuthenticator();
		$storage = $this->app['clockwork']->getStorage();

		$authenticated = $authenticator->check($this->app['request']->header('X-Clockwork-Auth'));

		if ($authenticated !== true) {
			return new JsonResponse([ 'message' => $authenticated, 'requires' => $authenticator->requires() ], 403);
		}

		if ($direction == 'previous') {
			$data = $storage->previous($id, $count);
		} elseif ($direction == 'next') {
			$data = $storage->next($id, $count);
		} elseif ($id == 'latest') {
			$data = $storage->latest();
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
				$this->getConfig('storage_files_path', storage_path('clockwork')), 0700, $expiration
			);
		}

		$storage->filter = $this->getFilter();

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

	public function getFilter()
	{
		return $this->getConfig('filter', []);
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

		$this->app['clockwork.laravel']->setResponse($response);

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

	public function configureSerializer()
	{
		Serializer::defaults([
			'limit'    => $this->getConfig('serialization_depth'),
			'blackbox' => $this->getConfig('serialization_blackbox')
		]);
	}

	public function isEnabled()
	{
		$isEnabled = $this->getConfig('enable', null);

		if ($isEnabled === null) {
			$isEnabled = $this->app['config']->get('app.debug');
		}

		return $isEnabled;
	}

	public function isCollectingData()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false))
			&& ! $this->app->runningInConsole()
			&& ! $this->isUriFiltered($this->app['request']->getRequestUri());
	}

	public function isCollectingDatabaseQueries()
	{
		return $this->app['config']->get('database.default') && ! in_array('databaseQueries', $this->getFilter());
	}

	public function isCollectingCacheStats()
	{
		return ! in_array('cache', $this->getFilter());
	}

	public function isCollectingEvents()
	{
		return ! in_array('events', $this->getFilter());
	}

	public function isCollectingViews()
	{
		return ! in_array('viewsData', $this->getFilter());
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
