<?php namespace Clockwork\Support\Laravel;

use Illuminate\Foundation\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ClockworkLegacyMiddleware implements HttpKernelInterface
{
	protected $kernel;
	protected $app;

	public function __construct(HttpKernelInterface $kernel, Application $app)
	{
		$this->kernel = $kernel;
		$this->app = $app;
	}

	public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
	{
		$response = $this->kernel->handle($request, $type, $catch);
		
		return $this->app['clockwork.support']->process($request, $response);
	}
}
