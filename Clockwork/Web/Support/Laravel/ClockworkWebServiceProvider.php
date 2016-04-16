<?php namespace Clockwork\Web\Support\Laravel;

use Clockwork\Clockwork;
use Clockwork\Web\Web as ClockworkWeb;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ClockworkWebServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if (! $this->app['clockwork.web.support']->isEnabled()) {
			return; // Clockwork Web is disabled, don't register the routes
		}

		$this->app['router']->get('/__clockwork/app', 'Clockwork\Web\Support\Laravel\Controllers\ClockworkController@render');
		$this->app['router']->get('/__clockwork/{path}', 'Clockwork\Web\Support\Laravel\Controllers\ClockworkController@renderAsset')->where('path', '.+');

		$this->app['clockwork.web']->setCurrentRequestId($this->app['clockwork']->getRequest()->id);

		$this->app['view']->share('clockwork_web', $this->app['clockwork.web']->getIframe());
	}

	public function register()
	{
		$this->publishes(array(__DIR__ . '/config/clockwork-web.php' => config_path('clockwork-web.php')));

		$this->app->singleton('clockwork.web.support', function($app)
		{
			return new ClockworkWebSupport($app);
		});

		$this->app->singleton('clockwork.web', function($app)
		{
			return new ClockworkWeb();
		});

		if (! $this->app['clockwork.web.support']->isEnabled()) {
			return;
		}
	}

	public function provides()
	{
		return [ 'clockwork-web' ];
	}
}
