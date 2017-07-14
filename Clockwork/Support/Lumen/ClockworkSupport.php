<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Storage\FileStorage;
use Clockwork\Storage\SqlStorage;

use Illuminate\Http\JsonResponse;
use Laravel\Lumen\Application;
use Symfony\Component\HttpFoundation\Response;

class ClockworkSupport
{
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function getAdditionalDataSources()
	{
		return $this->getConfig('additional_data_sources', []);
	}

	public function getConfig($key, $default = null)
	{
		return env('CLOCKWORK_' . strtoupper($key), $default);
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		if (isset($this->app['session'])) {
			$this->app['session.store']->reflash();
		}

		$storage = $this->app['clockwork']->getStorage();

		if ($direction == 'previous') {
			$data = $storage->previous($id, $count);
		} elseif ($direction == 'next') {
			$data = $storage->next($id, $count);
		} elseif ($id == 'latest') {
			$data = $storage->latest();
		} else {
			$data = $storage->find($id);
		}

		return new JsonResponse($data);
	}

	public function getStorage()
	{
		if ($this->getConfig('storage', 'files') == 'sql') {
			$database = $this->getConfig('storage_sql_database', storage_path('clockwork.sqlite'));
			$table = $this->getConfig('storage_sql_table', 'clockwork');

			if ($this->app['config']->get("database.connections.{$database}")) {
				$database = $this->app['db']->connection($database)->getPdo();
			} else {
				$database = "sqlite:{$database}";
			}

			$storage = new SqlStorage($database, $table);
			$storage->initialize();
		} else {
			$storage = new FileStorage($this->getConfig('storage_files_path', storage_path('clockwork')));
		}

		$storage->filter = $this->getFilter();

		return $storage;
	}

	public function getFilter()
	{
		return $this->getConfig('filter', []);
	}

	public function process($request, $response)
	{
		if (! $this->isCollectingData()) {
			return $response; // Collecting data is disabled, return immediately
		}

		// don't collect data for configured URIs
		$requestUri = $request->getRequestUri();
		$filterUris = $this->getConfig('filter_uris', []);
		$filterUris[] = '/__clockwork(?:/.*)?'; // don't collect data for Clockwork requests

		foreach ($filterUris as $uri) {
			$regexp = '#' . str_replace('#', '\#', $uri) . '#';

			if (preg_match($regexp, $requestUri)) {
				return $response;
			}
		}

		if (! $response instanceof Response) {
			$response = new Response((string) $response);
		}

		$this->app['clockwork.lumen']->setResponse($response);

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

		$this->appendServerTimingHeader($response, $this->app['clockwork']->getRequest());

		return $response;
	}

	public function isEnabled()
	{
		$isEnabled = $this->getConfig('enable', null);

		if ($isEnabled === null) {
			$isEnabled = env('APP_DEBUG', false);
		}

		return $isEnabled;
	}

	public function isCollectingData()
	{
		return ($this->isEnabled() || $this->getConfig('collect_data_always', false)) && ! $this->app->runningInConsole();
	}

	public function isCollectingDatabaseQueries()
	{
		return $this->app->bound('db') && $this->app['config']->get('database.default') && ! in_array('databaseQueries', $this->getFilter());
	}

	public function isCollectingEmails()
	{
		return $this->app->bound('mailer');
	}

	protected function appendServerTimingHeader($response, $request)
	{
		if (($eventsCount = $this->getConfig('server_timing', 10)) !== false) {
			$response->headers->set('Server-Timing', ServerTiming::fromRequest($request, $eventsCount)->value());
		}
	}
}
