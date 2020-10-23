<?php namespace Clockwork\Support\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;

// Clockwork Laravel middleware
class ClockworkMiddleware
{
	// Laravel application instance
	protected $app;

	// Create a new middleware instance
	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	// Handle an incoming request
	public function handle($request, \Closure $next)
	{
		$this->app['clockwork']->event('Controller')->begin();

		try {
			$response = $next($request);
		} catch (\Exception $e) {
			$this->app[ExceptionHandler::class]->report($e);
			$response = $this->app[ExceptionHandler::class]->render($request, $e);
		}

		return $this->app['clockwork.support']->processRequest($request, $response);
	}

	// Record the current request after a response is sent
	public function terminate()
	{
		$this->app['clockwork.support']->recordRequest();
	}
}
