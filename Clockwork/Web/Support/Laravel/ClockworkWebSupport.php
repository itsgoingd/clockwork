<?php namespace Clockwork\Web\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\Storage\FileStorage;
use Clockwork\Storage\SqlStorage;

use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;

class ClockworkWebSupport
{
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function getConfig($key, $default = null)
	{
		return $this->app['config']->get("clockwork-web.{$key}", $default);
	}

	public function isEnabled()
	{
		$is_enabled = $this->getConfig('enable', null);

		if ($is_enabled === null) {
			$is_enabled = $this->app['clockwork.support']->isEnabled();
		}

		return $is_enabled;
	}
}
