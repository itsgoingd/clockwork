<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\LumenDataSource;
use Clockwork\DataSource\EloquentDataSource;
use Clockwork\DataSource\SwiftDataSource;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

class ClockworkServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if ($this->isRunningWithFacades() && !class_exists('Clockwork')) {
			class_alias('Clockwork\Support\Lumen\Facade', 'Clockwork');
		}

		if (!$this->app['clockwork.support']->isCollectingData()) {
			return; // Don't bother registering event listeners as we are not collecting data
		}

		if ($this->app['clockwork.support']->isCollectingDatabaseQueries()) {
			$this->app['clockwork.eloquent']->listenToEvents();
		}

		if ($this->app['clockwork.support']->isCollectingEmails()) {
			$this->app->make('clockwork.swift');
		}

		if (!$this->app['clockwork.support']->isEnabled()) {
			return; // Clockwork is disabled, don't register the route
		}

		$this->app->get('/__clockwork/{id}', 'Clockwork\Support\Lumen\Controller@getData');
	}

	public function register()
	{
		$this->app->singleton('clockwork.support', function($app)
		{
			return new ClockworkSupport($app);
		});

		$this->app->singleton('clockwork.lumen', function($app)
		{
			return new LumenDataSource($app);
		});

		$this->app->singleton('clockwork.swift', function($app)
		{
			return new SwiftDataSource($app['mailer']->getSwiftMailer());
		});

		$this->app->singleton('clockwork.eloquent', function($app)
        {
            return new EloquentDataSource($app['db'], $app['events']);
        });

		foreach ($this->app['clockwork.support']->getAdditionalDataSources() as $name => $callable) {
			$this->app->singleton($name, $callable);
		}

		$this->app->singleton('clockwork', function($app)
		{
			$clockwork = new Clockwork();

			$clockwork
				->addDataSource(new PhpDataSource())
				->addDataSource($app['clockwork.lumen']);

			if ($app['clockwork.support']->isCollectingDatabaseQueries()) {
				$clockwork->addDataSource($app['clockwork.eloquent']);
			}

			if ($app['clockwork.support']->isCollectingEmails()) {
				$clockwork->addDataSource($app['clockwork.swift']);
			}

			foreach ($app['clockwork.support']->getAdditionalDataSources() as $name => $callable) {
				$clockwork->addDataSource($app[$name]);
			}

			$clockwork->setStorage($app['clockwork.support']->getStorage());

			return $clockwork;
		});

		$this->app['clockwork.lumen']->listenToEvents();

		// set up aliases for all Clockwork parts so they can be resolved by the IoC container
		$this->app->alias('clockwork.support', 'Clockwork\Support\Lumen\ClockworkSupport');
		$this->app->alias('clockwork.lumen', 'Clockwork\DataSource\LumenDataSource');
		$this->app->alias('clockwork.swift', 'Clockwork\DataSource\SwiftDataSource');
		$this->app->alias('clockwork.eloquent', 'Clockwork\DataSource\EloquentDataSource');
		$this->app->alias('clockwork', 'Clockwork\Clockwork');

		$this->registerCommands();

		if ($this->app['clockwork.support']->getConfig('register_helpers', true)) {
			require __DIR__ . '/helpers.php';
		}
	}

	/**
	 * Register the artisan commands.
	 */
	public function registerCommands()
	{
		// Clean command
		$this->app['command.clockwork.clean'] = $this->app->share(function($app){
			return new ClockworkCleanCommand();
		});

		$this->commands(
			'command.clockwork.clean'
		);
	}

	public function provides()
	{
		return array('clockwork');
	}

	protected function isRunningWithFacades()
	{
		return Facade::getFacadeApplication() !== null;
	}
}
