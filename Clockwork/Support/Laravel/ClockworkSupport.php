<?php namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;

use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;

class ClockworkSupport
{
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function getData($id = null, $last = null)
	{
		$this->app['session.store']->reflash();

		return new JsonResponse($this->app['clockwork']->getStorage()->retrieve($id, $last));
	}

	public function process($request, $response)
	{
		if (!$this->isCollectingData()) {
			return $response; // Collecting data is disabled, return immediately
		}

		// don't collect data for configured URIs
		$request_uri = $request->getRequestUri();
		$filter_uris = $this->app['config']->get('clockwork::config.filter_uris', array());
		$filter_uris[] = '/__clockwork/[0-9\.]+'; // don't collect data for Clockwork requests

		foreach ($filter_uris as $uri) {
			$regexp = '#' . str_replace('#', '\#', $uri) . '#';

			if (preg_match($regexp, $request_uri)) {
				return $response;
			}
		}

		$this->app['clockwork.laravel']->setResponse($response);

		$this->app['clockwork']->resolveRequest();
		$this->app['clockwork']->storeRequest();

		if (!$this->isEnabled()) {
			return; // Clockwork is disabled, don't set the headers
		}

		$response->headers->set('X-Clockwork-Id', $this->app['clockwork']->getRequest()->id, true);
		$response->headers->set('X-Clockwork-Version', Clockwork::VERSION, true);

		if ($request->getBasePath()) {
			$response->headers->set('X-Clockwork-Path', $request->getBasePath() . '/__clockwork/', true);
		}

		$extra_headers = $this->app['config']->get('clockwork::config.headers');
		if ($extra_headers && is_array($extra_headers)) {
			foreach ($extra_headers as $header_name => $header_value) {
				$response->headers->set('X-Clockwork-Header-' . $header_name, $header_value);
			}
		}

		return $response;
	}

	public function isEnabled()
	{
		$is_enabled = $this->app['config']->get('clockwork::config.enable', null);

		if ($is_enabled === null) {
			$is_enabled = $this->app['config']->get('app.debug');
		}

		return $is_enabled;
	}

	public function isCollectingData()
	{
		return $this->isEnabled() || $this->app['config']->get('clockwork::config.collect_data_always', false);
	}
}
