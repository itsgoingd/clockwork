<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;
use Clockwork\Support\Lumen\ClockworkSupport;

use Closure;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;

class ClockworkMiddleware
{
	/**
	 * Clockwork support instance
	 */
	protected $clockworkSupport;

	/**
	 * Exception handler instance
	 */
	protected $exceptionHandler;

	/**
	 * Create a new middleware instance.
	 */
	public function __construct(ClockworkSupport $clockworkSupport, ExceptionHandler $exceptionHandler)
	{
		$this->clockworkSupport = $clockworkSupport;
		$this->exceptionHandler = $exceptionHandler;
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
			$this->exceptionHandler->report($e);
			$response = $this->exceptionHandler->render($request, $e);
		}

		return $this->clockworkSupport->process($request, $response);
	}
}
