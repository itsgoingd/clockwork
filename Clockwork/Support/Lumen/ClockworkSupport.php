<?php namespace Clockwork\Support\Lumen;

use Clockwork\Support\Laravel\ClockworkSupport as LaravelSupport;

use Laravel\Lumen\Application;
use Symfony\Component\HttpFoundation\Response;

// Support class for the Lumen integration
class ClockworkSupport extends LaravelSupport
{
	// Lumen application instance
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	// Resolves the framework data source from the container
	protected function frameworkDataSource()
	{
		return $this->app['clockwork.lumen'];
	}

	// Process an http request and response, resolves the request, sets Clockwork headers and cookies
	public function process($request, $response)
	{
		if (! $response instanceof Response) {
			$response = new Response((string) $response);
		}

		return parent::process($request, $response);
	}

	// Set response on the framework data source
	protected function setResponse($response)
	{
		$this->app['clockwork.lumen']->setResponse($response);
	}

	// Check whether Clockwork is enabled
	public function isEnabled()
	{
		return $this->getConfig('enable')
			|| $this->getConfig('enable') === null && env('APP_DEBUG', false);
	}

	// Check whether a particular feature is available
	public function isFeatureAvailable($feature)
	{
		if ($feature == 'database') {
			return $this->app->bound('db') && $this->app['config']->get('database.default');
		} elseif ($feature == 'emails') {
			return $this->app->bound('mailer');
		} elseif ($feature == 'redis') {
			return $this->app->bound('redis') && method_exists(\Illuminate\Redis\RedisManager::class, 'enableEvents');
		} elseif ($feature == 'queue') {
			return $this->app->bound('queue') && method_exists(\Illuminate\Queue\Queue::class, 'createPayloadUsing');
		} elseif ($feature == 'xdebug') {
			return in_array('xdebug', get_loaded_extensions());
		}

		return true;
	}
}
