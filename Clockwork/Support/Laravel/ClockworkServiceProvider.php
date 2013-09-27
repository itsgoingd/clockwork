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

		$isEnabled = $this->app['config']->get('clockwork::enable');
		if ($isEnabled === null) {
			$isEnabled = $this->app['config']->get('app.debug');
		}

		$isCollectingData = $isEnabled || $this->app['config']->get('clockwork::collect_data_always', true);

		if (!$isCollectingData) {
			return; // Don't bother creating all the objects when we are not collecting data
		}

		$this->app['clockwork.timeline'] = $this->app->share(function($app){
			return new Timeline();
		});

		$this->app['clockwork.laravel'] = $this->app->share(function($app){
			$datasource = new LaravelDataSource($app);
			$datasource->setTimeline($app['clockwork.timeline']);

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
			if ($app['config']->get('clockwork::skip'))
				return;

			$app['clockwork.laravel']->setResponse($response);

			$app['clockwork']->resolveRequest();
			$app['clockwork']->storeRequest();

			if ($isEnabled) {
				$response->headers->set('X-Clockwork-Id', $app['clockwork']->getRequest()->id, true);
				$response->headers->set('X-Clockwork-Version', Clockwork::VERSION, true);

				if ($app['request']->getBasePath()) {
					$response->headers->set('X-Clockwork-Path', $app['request']->getBasePath() . '/__clockwork/', true);
				}
			}
		});

		if (!$isEnabled) {
			return; // Don't bother registering the route when we are sending 404 anyway
		}

		$this->app['router']->get('/__clockwork/{id}', function($id = null, $last = null) use($app){
			$app['config']->set('clockwork::skip', true);

			return $app['clockwork']->getStorage()->retrieveAsJson($id, $last);
		})->where('id', '[0-9\.]+');
	}

	public function provides()
	{
		return array('clockwork');
	}
}
