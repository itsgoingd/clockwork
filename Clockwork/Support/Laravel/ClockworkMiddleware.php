<?php namespace Clockwork\Support\Laravel;

use Illuminate\Foundation\Application;

class ClockworkMiddleware
{
	/**
	 * The Laravel Application
	 */
	protected $app;

	/**
	 * Create a new middleware instance.
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	/**
	 * Handle an incoming request.
	 */
	public function handle($request, \Closure $next)
	{
		$this->app['config']->set('clockwork::config.middleware', true);

		try {
			$response = $next($request);
		} catch (\Exception $e) {
			$this->app['Illuminate\Contracts\Debug\ExceptionHandler']->report($e);
			$response = $this->app['Illuminate\Contracts\Debug\ExceptionHandler']->render($request, $e);
		}

		return $this->app['clockwork.support']->process($request, $response);
	}
}
