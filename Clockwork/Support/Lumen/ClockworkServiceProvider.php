<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;
use Clockwork\DataSource\LumenDataSource;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\Request\Log;
use Clockwork\Support\Laravel\ClockworkServiceProvider as LaravelServiceProvider;

use Illuminate\Support\Facades\Facade;

class ClockworkServiceProvider extends LaravelServiceProvider
{
	protected function listenToFrameworkEvents()
	{
		$this->app['clockwork.lumen']->listenToEvents();
	}

	public function register()
	{
		$this->app->configure('clockwork');
		$this->mergeConfigFrom(__DIR__ . '/../Laravel/config/clockwork.php', 'clockwork');

		$this->app->singleton('clockwork', function ($app) {
			$support = $app['clockwork.support'];

			$clockwork = (new Clockwork)
				->setAuthenticator($app['clockwork.authenticator'])
				->setLog($app['clockwork.log'])
				->setStorage($app['clockwork.storage'])
				->addDataSource(new PhpDataSource())
				->addDataSource($app['clockwork.lumen']);

			if ($support->isFeatureEnabled('database')) $clockwork->addDataSource($app['clockwork.eloquent']);
			if ($support->isFeatureEnabled('cache')) $clockwork->addDataSource($app['clockwork.cache']);
			if ($support->isFeatureEnabled('redis')) $clockwork->addDataSource($app['clockwork.redis']);
			if ($support->isFeatureEnabled('queue')) $clockwork->addDataSource($app['clockwork.queue']);
			if ($support->isFeatureEnabled('events')) $clockwork->addDataSource($app['clockwork.events']);
			if ($support->isFeatureEnabled('emails')) $clockwork->addDataSource($app['clockwork.swift']);
			if ($support->isFeatureAvailable('xdebug')) $clockwork->addDataSource($app['clockwork.xdebug']);

			return $clockwork;
		});

		$this->app->singleton('clockwork.authenticator', function ($app) {
			return $app['clockwork.support']->getAuthenticator();
		});

		$this->app->singleton('clockwork.log', function ($app) {
			return new Log;
		});

		$this->app->singleton('clockwork.storage', function ($app) {
			return $app['clockwork.support']->getStorage();
		});

		$this->app->singleton('clockwork.support', function ($app) {
			return new ClockworkSupport($app);
		});

		$this->registerCommands();
		$this->registerDataSources();
		$this->registerAliases();

		$this->app['clockwork.support']->configureSerializer();

		if ($this->isRunningWithFacades() && ! class_exists('Clockwork')) {
			class_alias(\Clockwork\Support\Laravel\Facade::class, 'Clockwork');
		}

		if ($this->app['clockwork.support']->getConfig('register_helpers', true)) {
			require __DIR__ . '/../Laravel/helpers.php';
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
			))
				->setLog($app['clockwork.log']);
		});
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
		$router->post('/__clockwork/auth', 'Clockwork\Support\Lumen\Controller@authenticate');
	}

	public function registerWebRoutes()
	{
		$router = isset($this->app->router) ? $this->app->router : $this->app;

		$router->get('/__clockwork', 'Clockwork\Support\Lumen\Controller@webRedirect');
		$router->get('/__clockwork/app', 'Clockwork\Support\Lumen\Controller@webIndex');
		$router->get('/__clockwork/{path:.+}', 'Clockwork\Support\Lumen\Controller@webAsset');
	}

	protected function isRunningWithFacades()
	{
		return Facade::getFacadeApplication() !== null;
	}

	public function provides()
	{
		return [ 'clockwork' ];
	}
}
