<?php namespace Clockwork\Support\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;

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
		try {
			$response = $next($request);
		} catch (\Exception $e) {
			$this->app[ExceptionHandler::class]->report($e);
			$response = $this->app[ExceptionHandler::class]->render($request, $e);
		}

		return $this->app['clockwork.support']->process($request, $response);
	}
}
