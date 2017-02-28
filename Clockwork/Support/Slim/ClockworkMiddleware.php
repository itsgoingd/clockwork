<?php namespace Clockwork\Support\Slim;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\SlimDataSource;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Storage\FileStorage;

use Slim\Middleware;

class ClockworkMiddleware extends Middleware
{
	private $storagePathOrClockwork;

	public function __construct($storagePathOrClockwork)
	{
		$this->storagePathOrClockwork = $storagePathOrClockwork;
	}

	public function call()
	{
		$this->app->container->singleton('clockwork', function () {
			if ($this->storagePathOrClockwork instanceof Clockwork) {
				return $this->storagePathOrClockwork;
			}

			$clockwork = new Clockwork();

			$clockwork->addDataSource(new PhpDataSource())
				->addDataSource(new SlimDataSource($this->app))
				->setStorage(new FileStorage($this->storagePathOrClockwork));

			return $clockwork;
		});

		$originalLogWriter = $this->app->getLog()->getWriter();
		$clockworkLogWriter = new ClockworkLogWriter($this->app->clockwork, $originalLogWriter);

		$this->app->getLog()->setWriter($clockworkLogWriter);

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
