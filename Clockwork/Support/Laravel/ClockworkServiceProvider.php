<?php
namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\LaravelDataSource;
use Clockwork\DataSource\EloquentDataSource;
use Clockwork\DataSource\SwiftDataSource;
use Clockwork\Request\Timeline;
use Clockwork\Storage\FileStorage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\JsonResponse;

class ClockworkServiceProvider extends ServiceProvider
{
	public function boot()
	{
		$this->package('itsgoingd/clockwork', 'clockwork', __DIR__);

		if (!$this->isCollectingData()) {
			return; // Don't bother registering event listeners as we are not collecting data
		}

		$this->app['clockwork.laravel']->listenToEvents();
		$this->app->make('clockwork.swift');

		if (!$this->isEnabled()) {
			return; // Clockwork is diabled, don't register the route
		}

		$app = $this->app;
		$this->app['router']->get('/__clockwork/{id}', function($id = null, $last = null) use($app)
		{
			$app['session.store']->reflash();
			return new JsonResponse($app['clockwork']->getStorage()->retrieve($id, $last));
		})->where('id', '[0-9\.]+');
	}

	public function register()
	{
		$this->app->singleton('clockwork.laravel', function($app)
		{
			return new LaravelDataSource($app);
		});

		$this->app->singleton('clockwork.swift', function($app)
		{
			return new SwiftDataSource($app['mailer']->getSwiftMailer());
		});

		$this->app->singleton('clockwork', function($app)
		{
			$clockwork = new Clockwork();

			$clockwork
				->addDataSource(new PhpDataSource())
				->addDataSource($app['clockwork.laravel'])
				->addDataSource($app['clockwork.swift']);

			$filter = $app['config']->get('clockwork::config.filter', array());

			if ($app['config']->get('database.default') && !in_array('databaseQueries', $filter)) {
				$clockwork->addDataSource(new EloquentDataSource($app['db']->connection()));
			}

			$storage = new FileStorage($app['path.storage'] . '/clockwork');
			$storage->filter = $filter;

			$clockwork->setStorage($storage);

			return $clockwork;
		});

		$this->registerCommands();

		$app = $this->app;
		$service = $this;
		$this->app->after(function($request, $response) use($app, $service)
		{
			if (!$service->isCollectingData()) {
				return; // Collecting data is disabled, return immediately
			}

			// don't collect data for configured URIs
			$request_uri = $app['request']->getRequestUri();
			$filter_uris = $app['config']->get('clockwork::config.filter_uris', array());
			$filter_uris[] = '/__clockwork/[0-9\.]+'; // don't collect data for Clockwork requests

			foreach ($filter_uris as $uri) {
				$regexp = '#' . str_replace('#', '\#', $uri) . '#';

				if (preg_match($regexp, $request_uri)) {
					return;
				}
			}

			$app['clockwork.laravel']->setResponse($response);

			$app['clockwork']->resolveRequest();
			$app['clockwork']->storeRequest();

			if (!$service->isEnabled()) {
				return; // Clockwork is disabled, don't set the headers
			}

			$response->headers->set('X-Clockwork-Id', $app['clockwork']->getRequest()->id, true);
			$response->headers->set('X-Clockwork-Version', Clockwork::VERSION, true);

			if ($app['request']->getBasePath()) {
				$response->headers->set('X-Clockwork-Path', $app['request']->getBasePath() . '/__clockwork/', true);
			}
			
			$extra_headers = $app['config']->get('clockwork::config.headers');
			if ($extra_headers && is_array($extra_headers)) {
				foreach ($extra_headers as $header_name => $header_value) {
					$response->headers->set('X-Clockwork-Header-' . $header_name, $header_value);
				}
			}
		});
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
