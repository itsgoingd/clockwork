<?php
namespace Clockwork\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\LaravelDataSource;
use Clockwork\DataSource\EloquentDataSource;
use Clockwork\Request\Timeline;
use Clockwork\Storage\FileStorage;
use Illuminate\Support\ServiceProvider;

class ClockworkServiceProvider extends ServiceProvider
{
	public function boot()
	{
	}

	public function register()
	{
		$this->app['config']->package('itsgoingd/clockwork', __DIR__ . '/config');

		$this->registerCommands();

		$isEnabled = $this->app['config']->get('clockwork::enable');
		if ($isEnabled === null) {
			$isEnabled = $this->app['config']->get('app.debug');
		}

		$isCollectingData = $isEnabled || $this->app['config']->get('clockwork::collect_data_always', true);

		if (!$isCollectingData) {
			return; // Don't bother creating all the objects when we are not collecting data
		}

		$this->app['clockwork.laravel'] = $this->app->share(function($app){
			$datasource = new LaravelDataSource($app);

			return $datasource;
		});

		$this->app['clockwork.laravel']->listenToEvents();

		$this->app['clockwork'] = $this->app->share(function($app){
			$clockwork = new Clockwork();

			$clockwork
				->addDataSource(new PhpDataSource())
				->addDataSource($app['clockwork.laravel']);

			$filter = $app['config']->get('clockwork::filter', array());

			if ($app['config']->get('database.default') && !in_array('databaseQueries', $filter))
				$clockwork->addDataSource(new EloquentDataSource($app['db']->connection()));

			$storage = new FileStorage($app['path.storage'] . '/clockwork');
			$storage->filter = $filter;

			$clockwork->setStorage($storage);

			return $clockwork;
		});

		$app = $this->app;
		$this->app->after(function($request, $response) use($app, $isEnabled){
			// don't collect data for configured URIs

			$request_uri = $app['request']->getRequestUri();
			$filter_uris = $app['config']->get('clockwork::filter_uris', array());
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

			if ($isEnabled) {
				$response->headers->set('X-Clockwork-Id', $app['clockwork']->getRequest()->id, true);
				$response->headers->set('X-Clockwork-Version', Clockwork::VERSION, true);

				if ($app['request']->getBasePath()) {
					$response->headers->set('X-Clockwork-Path', $app['request']->getBasePath() . '/__clockwork/', true);
				}
				
				$extraHeaders = $this->app['config']->get('clockwork::headers');
				if ($extraHeaders and is_array($extraHeaders)) {
					foreach ($extraHeaders as $headerName => $headerValue) {
						$response->headers->set('X-Clockwork-Header-'.$headerName, $headerValue);
					}
				}
			}
		});

		if (!$isEnabled) {
			return; // Don't bother registering the route when we are sending 404 anyway
		}

		$this->app['router']->get('/__clockwork/{id}', function($id = null, $last = null) use($app){
			return $app['clockwork']->getStorage()->retrieveAsJson($id, $last);
		})->where('id', '[0-9\.]+');
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
}
