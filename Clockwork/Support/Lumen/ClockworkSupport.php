<?php namespace Clockwork\Support\Lumen;

use Clockwork\Support\Laravel\ClockworkSupport as LaravelSupport;

use Laravel\Lumen\Application;
use Symfony\Component\HttpFoundation\Response;

class ClockworkSupport extends LaravelSupport
{
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function process($request, $response)
	{
		if (! $response instanceof Response) {
			$response = new Response((string) $response);
		}

		return parent::process($request, $response);
	}

	protected function setResponse($response)
	{
		$this->app['clockwork.lumen']->setResponse($response);
	}

	public function isEnabled()
	{
		return $this->getConfig('enable')
			|| $this->getConfig('enable') === null && env('APP_DEBUG', false);
	}

	public function isFeatureAvailable($feature)
	{
		if ($feature == 'database') {
			return $this->app->bound('db') && $this->app['config']->get('database.default');
		} elseif ($feature == 'emails') {
			return $this->app->bound('mailer');
		} elseif ($feature == 'redis') {
			return method_exists(\Illuminate\Redis\RedisManager::class, 'enableEvents');
		} elseif ($feature == 'queue') {
			return method_exists(\Illuminate\Queue\Queue::class, 'createPayloadUsing');
		} elseif ($feature == 'xdebug') {
			return in_array('xdebug', get_loaded_extensions());
		}

		return true;
	}
}
