<?php
namespace Clockwork\Support\Slim;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\SlimDataSource;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Storage\FileStorage;

use Slim\Middleware;

class ClockworkMiddleware extends Middleware
{
	private $storage_path_or_clockwork;

	public function __construct($storage_path_or_clockwork)
	{
		$this->storage_path_or_clockwork = $storage_path_or_clockwork;
	}

	public function call()
	{
		$app = $this->app;
		$storage_path_or_clockwork = $this->storage_path_or_clockwork;

		$this->app->container->singleton('clockwork', function() use($app, $storage_path_or_clockwork)
		{
			if ($storage_path_or_clockwork instanceof Clockwork) {
				return $storage_path_or_clockwork;
			}

			$clockwork = new Clockwork();

			$clockwork->addDataSource(new PhpDataSource())
				->addDataSource(new SlimDataSource($app))
				->setStorage(new FileStorage($storage_path_or_clockwork));

			return $clockwork;
		});

		$original_log_writer = $this->app->getLog()->getWriter();
		$clockwork_log_writer = new ClockworkLogWriter($this->app->clockwork, $original_log_writer);

		$this->app->getLog()->setWriter($clockwork_log_writer);

		if ($this->app->config('debug') && preg_match('#/__clockwork(/(?<id>[0-9\.]+))?#', $this->app->request()->getPathInfo(), $matches)) {
			return $this->retrieveRequest($matches['id']);
		}

		try {
			$this->next->call();
			$this->logRequest();
		} catch (Exception $e) {
			$this->logRequest();
			throw $e;
		}
	}

	public function retrieveRequest($id = null, $last = null)
	{
		echo $this->app->clockwork->getStorage()->retrieveAsJson($id, $last);
	}

	protected function logRequest()
	{
		$this->app->clockwork->resolveRequest();
		$this->app->clockwork->storeRequest();

		if ($this->app->config('debug')) {
			$this->app->response()->header('X-Clockwork-Id', $this->app->clockwork->getRequest()->id);
			$this->app->response()->header('X-Clockwork-Version', Clockwork::VERSION);

			$env = $this->app->environment();
			if ($env['SCRIPT_NAME']) {
				$this->app->response()->header('X-Clockwork-Path', $env['SCRIPT_NAME'] . '/__clockwork/');
			}

			$request = $this->app->clockwork->getRequest();
			$this->app->response()->header('Server-Timing', ServerTiming::fromRequest($request)->value());
		}
	}
}
