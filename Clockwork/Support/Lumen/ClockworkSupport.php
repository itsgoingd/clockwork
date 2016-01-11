<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;
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
		return $this->getConfig('additional_data_sources', array());
	}

	public function getConfig($key, $default = null)
	{
		return env('CLOCKWORK_' . strtoupper($key), $default);
	}

	public function getData($id = null, $last = null)
	{
		if (isset($this->app['session'])) {
			$this->app['session.store']->reflash();
		}

		return new JsonResponse($this->app['clockwork']->getStorage()->retrieve($id, $last));
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
		return $this->getConfig('filter', array());
	}

	public function process($request, $response)
	{
		if (!$this->isCollectingData()) {
			return $response; // Collecting data is disabled, return immediately
		}

		// don't collect data for configured URIs
		$request_uri = $request->getRequestUri();
		$filter_uris = $this->getConfig('filter_uris', array());
		$filter_uris[] = '/__clockwork/[0-9\.]+'; // don't collect data for Clockwork requests

		foreach ($filter_uris as $uri) {
			$regexp = '#' . str_replace('#', '\#', $uri) . '#';

			if (preg_match($regexp, $request_uri)) {
				return $response;
			}
		}

		if (! $response instanceof Response) {
			$response = new Response((string) $response);
		}

		$this->app['clockwork.lumen']->setResponse($response);

		$this->app['clockwork']->resolveRequest();
		$this->app['clockwork']->storeRequest();

		if (!$this->isEnabled()) {
			return $response; // Clockwork is disabled, don't set the headers
		}

		$response->headers->set('X-Clockwork-Id', $this->app['clockwork']->getRequest()->id, true);
		$response->headers->set('X-Clockwork-Version', Clockwork::VERSION, true);

		if ($request->getBasePath()) {
			$response->headers->set('X-Clockwork-Path', $request->getBasePath() . '/__clockwork/', true);
		}

		$extra_headers = $this->getConfig('headers', array());
		foreach ($extra_headers as $header_name => $header_value) {
			$response->headers->set('X-Clockwork-Header-' . $header_name, $header_value);
		}

		return $response;
	}

	public function isEnabled()
	{
		$is_enabled = $this->getConfig('enable', null);

		if ($is_enabled === null) {
			$is_enabled = env('APP_DEBUG', false);
		}

		return $is_enabled;
	}

	public function isCollectingData()
	{
		return $this->isEnabled() || $this->getConfig('collect_data_always', false);
	}

	public function isCollectingDatabaseQueries()
	{
		return $this->app->bound('db') && $this->app['config']->get('database.default') && !in_array('databaseQueries', $this->getFilter());
	}

	public function isCollectingEmails()
	{
		return $this->app->bound('mailer');
	}
}
