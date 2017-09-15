<?php namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\LaravelDataSource;
use Clockwork\DataSource\EloquentDataSource;
use Clockwork\DataSource\SwiftDataSource;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ClockworkServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if (!$this->app['clockwork.support']->isCollectingData()) {
			return; // Don't bother registering event listeners as we are not collecting data
		}

		$this->app['clockwork.eloquent']->listenToEvents();

		// create the clockwork instance so all data sources are initialized at this point
		$this->app->make('clockwork');

		if (!$this->app['clockwork.support']->isEnabled()) {
			return; // Clockwork is disabled, don't register the route
		}

		if ($this->isLegacyLaravel()) {
			$this->app['router']->get('/__clockwork/{id}', 'Clockwork\Support\Laravel\Controllers\LegacyController@getData')->where('id', '[0-9\.]+');
		} elseif ($this->isOldLaravel()) {
			$this->app['router']->get('/__clockwork/{id}', 'Clockwork\Support\Laravel\Controllers\OldController@getData')->where('id', '[0-9\.]+');
		} else {
			$this->app['router']->get('/__clockwork/{id}', 'Clockwork\Support\Laravel\Controllers\CurrentController@getData')->where('id', '[0-9\.]+');
		}
	}

	public function register()
	{
		if ($this->isLegacyLaravel() || $this->isOldLaravel()) {
			$this->package('itsgoingd/clockwork', 'clockwork', __DIR__);
		} else {
			$this->publishes(array(__DIR__ . '/config/clockwork.php' => config_path('clockwork.php')));
		}

		$legacy = $this->isLegacyLaravel() || $this->isOldLaravel();
		$this->app->singleton('clockwork.support', function($app) use($legacy)
		{
			return new ClockworkSupport($app, $legacy);
		});

		$this->app->singleton('clockwork.laravel', function($app)
		{
			return new LaravelDataSource($app);
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
				->addDataSource($app['clockwork.laravel'])
				->addDataSource($app['clockwork.swift']);

			if ($app['clockwork.support']->isCollectingDatabaseQueries()) {
				$clockwork->addDataSource($app['clockwork.eloquent']);
			}

			foreach ($app['clockwork.support']->getAdditionalDataSources() as $name => $callable) {
				$clockwork->addDataSource($app[$name]);
			}

			$clockwork->setStorage($app['clockwork.support']->getStorage());

			return $clockwork;
		});

		$this->app['clockwork.laravel']->listenToEvents();

		// set up aliases for all Clockwork parts so they can be resolved by the IoC container
		$this->app->alias('clockwork.support', 'Clockwork\Support\Laravel\ClockworkSupport');
		$this->app->alias('clockwork.laravel', 'Clockwork\DataSource\LaravelDataSource');
		$this->app->alias('clockwork.swift', 'Clockwork\DataSource\SwiftDataSource');
		$this->app->alias('clockwork.eloquent', 'Clockwork\DataSource\EloquentDataSource');
		$this->app->alias('clockwork', 'Clockwork\Clockwork');

		$this->registerCommands();
		$this->registerMiddleware();

		if ($this->app['clockwork.support']->getConfig('register_helpers', true)) {
			require __DIR__ . '/helpers.php';
		}
	}

	// Register middleware
	public function registerMiddleware()
	{
		if ($this->isLegacyLaravel()) {
			$this->app->middleware('Clockwork\Support\Laravel\ClockworkLegacyMiddleware', array($this->app));
		} else if ($this->isOldLaravel()) {
			$app = $this->app;
			$this->app['router']->after(function($request, $response) use($app)
			{
				return $app['clockwork.support']->process($request, $response);
			});
		} else {
			$kernel = $this->app['Illuminate\Contracts\Http\Kernel'];
			$kernel->prependMiddleware('Clockwork\Support\Laravel\ClockworkMiddleware');
		}
	}

	/**
	 * Register the artisan commands.
	 */
	public function registerCommands()
	{
		// Clean command
		$this->app->bind('command.clockwork.clean', 'Clockwork\Support\Laravel\ClockworkCleanCommand');

		$this->commands(
			'command.clockwork.clean'
		);
	}

	public function provides()
	{
		return array('clockwork');
	}

	public function isLegacyLaravel()
	{
		return Str::startsWith(Application::VERSION, array('4.1', '4.2'));
	}

	public function isOldLaravel()
	{
		return Str::startsWith(Application::VERSION, '4.0');
	}
}
