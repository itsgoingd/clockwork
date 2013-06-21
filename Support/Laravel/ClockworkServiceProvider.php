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
				->addDataSource($app['clockwork.laravel'])
				->addDataSource(new EloquentDataSource($app['db']->connection()))
				->setStorage(new FileStorage($app['config']->get('paths.storage') . '/clockwork'));

			return $clockwork;
		});

		$app = $this->app;
		$this->app->after(function($request, $response) use($app){
			$app['clockwork.laravel']->setResponse($response);

			$app['clockwork']->resolveRequest();
			$app['clockwork']->storeRequest();

			if ($app['config']->get('app.debug') && !$app['config']->get('clockwork.skip')) {
				$response->headers->set('X-Clockwork-Id', $app['clockwork']->getRequest()->id, true);
				$response->headers->set('X-Clockwork-Version', '0.9.0', true);
			}
		});

		$this->app['router']->get('/__clockwork/{id}', function($id = null, $last = null) use($app){
			if (!$app['config']->get('app.debug'))
				$app->abort(404);

			$app['config']->set('clockwork.skip', true);

			return $app['clockwork']->getStorage()->retrieveAsJson($id, $last);
		});
	}

	public function provides()
	{
		return array('clockwork');
	}
}
