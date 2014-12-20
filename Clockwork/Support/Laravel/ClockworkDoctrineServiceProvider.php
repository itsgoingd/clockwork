<?php namespace Clockwork\Support\Laravel;

use Clockwork\DataSource\DoctrineDataSource;
use Illuminate\Support\ServiceProvider;

class ClockworkDoctrineServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $clockwork = $this->app['clockwork'];

        $clockwork->addDataSource($this->app['clockwork.doctrine']);

    }

    public function register()
    {
        $this->app->singleton('clockwork.doctrine', function($app)
        {
            return new DoctrineDataSource($app['Doctrine\ORM\EntityManager'], $app['log']);
        });
    }
}
