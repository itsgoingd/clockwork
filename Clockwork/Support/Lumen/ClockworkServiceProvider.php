<?php namespace Clockwork\Support\Lumen;

use Clockwork\DataSource\LumenDataSource;
use Clockwork\Support\Laravel\ClockworkServiceProvider as LaravelServiceProvider;

use Illuminate\Support\Facades\Facade;

class ClockworkServiceProvider extends LaravelServiceProvider
{
	protected function registerConfiguration()
	{
		$this->app->configure('clockwork');
		$this->mergeConfigFrom(__DIR__ . '/../Laravel/config/clockwork.php', 'clockwork');
	}

	protected function registerClockwork()
	{
		parent::registerClockwork();

		$this->app->singleton('clockwork.support', function ($app) {
			return new ClockworkSupport($app);
		});

		if ($this->isRunningWithFacades() && ! class_exists('Clockwork')) {
			class_alias(\Clockwork\Support\Laravel\Facade::class, 'Clockwork');
		}
	}

	protected function registerDataSources()
	{
		parent::registerDataSources();

		$this->app->singleton('clockwork.lumen', function ($app) {
			return (new LumenDataSource(
				$app,
				$app['clockwork.support']->isFeatureEnabled('log'),
				$app['clockwork.support']->isFeatureEnabled('views'),
				$app['clockwork.support']->isFeatureEnabled('routes')
			));
		});
	}

	protected function registerAliases()
	{
		parent::registerAliases();

		$this->app->alias('clockwork.lumen', LumenDataSource::class);
	}

	public function registerMiddleware()
	{
		$this->app->middleware([ ClockworkMiddleware::class ]);
	}

	public function registerRoutes()
	{
		$router = isset($this->app->router) ? $this->app->router : $this->app;

		$router->get('/__clockwork/{id:(?:[0-9-]+|latest)}/extended', 'Clockwork\Support\Lumen\Controller@getExtendedData');
		$router->get('/__clockwork/{id:(?:[0-9-]+|latest)}[/{direction:(?:next|previous)}[/{count:\d+}]]', 'Clockwork\Support\Lumen\Controller@getData');
		$router->put('/__clockwork/{id}', 'Clockwork\Support\Lumen\Controller@updateData');
		$router->post('/__clockwork/auth', 'Clockwork\Support\Lumen\Controller@authenticate');
	}

	public function registerWebRoutes()
	{
		$router = isset($this->app->router) ? $this->app->router : $this->app;

		$this->app['clockwork.support']->webPaths()->each(function ($path) use ($router) {
			$router->get("{$path}", 'Clockwork\Support\Lumen\Controller@webRedirect');
			$router->get("{$path}/app", 'Clockwork\Support\Lumen\Controller@webIndex');
			$router->get("{$path}/{path:.+}", 'Clockwork\Support\Lumen\Controller@webAsset');
		});
	}

	protected function frameworkDataSource()
	{
		return $this->app['clockwork.lumen'];
	}

	protected function isRunningWithFacades()
	{
		return Facade::getFacadeApplication() !== null;
	}
}
