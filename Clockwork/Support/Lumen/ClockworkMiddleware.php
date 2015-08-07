<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;

use Closure;
use Exception;
use Illuminate\Contracts\Routing\Middleware;
use Illuminate\Contracts\Foundation\Application;

class ClockworkMiddleware implements Middleware
{
	/**
	 * The Laravel Application
	 *
	 * @var Application
	 */
	protected $app;

	/**
	 * Create a new middleware instance.
	 *
	 * @param  Application  $app
	 * @return void
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		try {
			$response = $next($request);
		} catch (Exception $e) {
			$this->app['Illuminate\Contracts\Debug\ExceptionHandler']->report($e);
			$response = $this->app['Illuminate\Contracts\Debug\ExceptionHandler']->render($request, $e);
		}

		return $this->app['clockwork.support']->process($request, $response);
	}
}
