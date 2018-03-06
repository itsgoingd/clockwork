<?php namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\LaravelDataSource;
use Clockwork\DataSource\LaravelCacheDataSource;
use Clockwork\DataSource\LaravelEventsDataSource;
use Clockwork\DataSource\EloquentDataSource;
use Clockwork\DataSource\SwiftDataSource;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ClockworkServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if ($this->app['clockwork.support']->isCollectingData()) {
			$this->listenToEvents();
		}

		if (! $this->app['clockwork.support']->isEnabled()) {
			return; // Clockwork is disabled, don't register the middleware and routes
		}

		$this->registerRoutes();

		// register the Clockwork Web UI routes
		if ($this->app['clockwork.support']->isWebEnabled()) {
			$this->registerWebRoutes();
		}
	}

	protected function listenToEvents()
	{
		$this->app['clockwork.laravel']->listenToEvents();

		if ($this->app['clockwork.support']->isCollectingDatabaseQueries()) {
			$this->app['clockwork.eloquent']->listenToEvents();
		}

		if ($this->app['clockwork.support']->isCollectingCacheStats()) {
			$this->app['clockwork.cache']->listenToEvents();
		}

		if ($this->app['clockwork.support']->isCollectingEvents()) {
			$this->app['clockwork.events']->listenToEvents();
		}
	}

	public function register()
	{
		if ($this->isLegacyLaravel() || $this->isOldLaravel()) {
			$this->package('itsgoingd/clockwork', 'clockwork', __DIR__ . '/legacy');
		} else {
			$this->publishes([ __DIR__ . '/config/clockwork.php' => config_path('clockwork.php') ]);
			$this->mergeConfigFrom(__DIR__ . '/config/clockwork.php', 'clockwork');
		}

		$this->app->singleton('clockwork.support', function ($app) {
			return new ClockworkSupport($app, $this->isLegacyLaravel() || $this->isOldLaravel());
		});

		$this->app->singleton('clockwork.laravel', function ($app) {
			return new LaravelDataSource($app);
		});

		$this->app->singleton('clockwork.swift', function ($app) {
			return new SwiftDataSource($app['mailer']->getSwiftMailer());
		});

		$this->app->singleton('clockwork.eloquent', function ($app) {
			return new EloquentDataSource($app['db'], $app['events']);
		});

		$this->app->singleton('clockwork.cache', function ($app) {
			return new LaravelCacheDataSource($app['events']);
		});

		$this->app->singleton('clockwork.events', function ($app) {
			return new LaravelEventsDataSource(
				$app['events'], $app['clockwork.support']->getConfig('ignored_events', [])
			);
		});

		$this->app->singleton('clockwork', function ($app) {
			$clockwork = new Clockwork();

			$clockwork
				->addDataSource(new PhpDataSource())
				->addDataSource($app['clockwork.laravel'])
				->addDataSource($app['clockwork.swift']);

			if ($app['clockwork.support']->isCollectingDatabaseQueries()) {
				$clockwork->addDataSource($app['clockwork.eloquent']);
			}

			if ($app['clockwork.support']->isCollectingCacheStats()) {
				$clockwork->addDataSource($app['clockwork.cache']);
			}

			if ($app['clockwork.support']->isCollectingEvents()) {
				$clockwork->addDataSource($app['clockwork.events']);
			}

			$clockwork->setStorage($app['clockwork.support']->getStorage());

			return $clockwork;
		});

		$this->app['clockwork.laravel']->listenToEarlyEvents();

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

	// Register the artisan commands.
	public function registerCommands()
	{
		$this->commands([
			'Clockwork\Support\Laravel\ClockworkCleanCommand'
		]);
	}

	// Register middleware
	public function registerMiddleware()
	{
		if ($this->isLegacyLaravel()) {
			$this->app->middleware('Clockwork\Support\Laravel\ClockworkLegacyMiddleware', [ $this->app ]);
		} elseif ($this->isOldLaravel()) {
			$this->app['router']->after(function ($request, $response) {
				return $this->app['clockwork.support']->process($request, $response);
			});
		} else {
			$kernel = $this->app['Illuminate\Contracts\Http\Kernel'];
			$kernel->prependMiddleware('Clockwork\Support\Laravel\ClockworkMiddleware');
		}
	}

	public function registerRoutes()
	{
		if ($this->isLegacyLaravel()) {
			$this->app['router']->get('/__clockwork/{id}/{direction?}/{count?}', 'Clockwork\Support\Laravel\Controllers\LegacyController@getData')
				->where('id', '([0-9-]+|latest)')->where('direction', '(next|previous)')->where('count', '\d+');
		} elseif ($this->isOldLaravel()) {
			$this->app['router']->get('/__clockwork/{id}/{direction?}/{count?}', 'Clockwork\Support\Laravel\Controllers\OldController@getData')
				->where('id', '([0-9-]+|latest)')->where('direction', '(next|previous)')->where('count', '\d+');
		} else {
			$this->app['router']->get('/__clockwork/{id}/{direction?}/{count?}', 'Clockwork\Support\Laravel\Controllers\CurrentController@getData')
				->where('id', '([0-9-]+|latest)')->where('direction', '(next|previous)')->where('count', '\d+');
		}
	}

	public function registerWebRoutes()
	{
		if ($this->isLegacyLaravel()) {
			$this->app['router']->get('/__clockwork', 'Clockwork\Support\Laravel\Controllers\LegacyController@webRedirect');
			$this->app['router']->get('/__clockwork/app', 'Clockwork\Support\Laravel\Controllers\LegacyController@webIndex');
			$this->app['router']->get('/__clockwork/assets/{path}', 'Clockwork\Support\Laravel\Controllers\LegacyController@webAsset')->where('path', '.+');
		} elseif ($this->isOldLaravel()) {
			$this->app['router']->get('/__clockwork', 'Clockwork\Support\Laravel\Controllers\OldController@webRedirect');
			$this->app['router']->get('/__clockwork/app', 'Clockwork\Support\Laravel\Controllers\OldController@webIndex');
			$this->app['router']->get('/__clockwork/assets/{path}', 'Clockwork\Support\Laravel\Controllers\OldController@webAsset')->where('path', '.+');
		} else {
			$this->app['router']->get('/__clockwork', 'Clockwork\Support\Laravel\Controllers\CurrentController@webRedirect');
			$this->app['router']->get('/__clockwork/app', 'Clockwork\Support\Laravel\Controllers\CurrentController@webIndex');
			$this->app['router']->get('/__clockwork/assets/{path}', 'Clockwork\Support\Laravel\Controllers\CurrentController@webAsset')->where('path', '.+');
		}
	}

	public function provides()
	{
		return [ 'clockwork' ];
	}

	public function isLegacyLaravel()
	{
		return Str::startsWith(Application::VERSION, [ '4.1', '4.2' ]);
	}

	public function isOldLaravel()
	{
		return Str::startsWith(Application::VERSION, '4.0');
	}
}
