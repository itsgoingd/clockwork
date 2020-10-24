<?php namespace Clockwork\Support\Slim\Old;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\SlimDataSource;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Storage\FileStorage;

use Slim\Middleware;

// Slim 2 middleware
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
				->storage(new FileStorage($this->storagePathOrClockwork));

			return $clockwork;
		});

		$originalLogWriter = $this->app->getLog()->getWriter();
		$clockworkLogWriter = new ClockworkLogWriter($this->app->clockwork, $originalLogWriter);

		$this->app->getLog()->setWriter($clockworkLogWriter);

		$clockworkDataUri = '#/__clockwork(?:/(?<id>[0-9-]+))?(?:/(?<direction>(?:previous|next)))?(?:/(?<count>\d+))?#';
		if ($this->app->config('debug') && preg_match($clockworkDataUri, $this->app->request()->getPathInfo(), $matches)) {
			$matches = array_merge([ 'direction' => null, 'count' => null ], $matches);
			return $this->retrieveRequest($matches['id'], $matches['direction'], $matches['count']);
		}

		try {
			$this->next->call();
			$this->logRequest();
		} catch (Exception $e) {
			$this->logRequest();
			throw $e;
		}
	}

	public function retrieveRequest($id = null, $direction = null, $count = null)
	{
		$storage = $this->app->clockwork->storage();

		if ($direction == 'previous') {
			$data = $storage->previous($id, $count);
		} elseif ($direction == 'next') {
			$data = $storage->next($id, $count);
		} elseif ($id == 'latest') {
			$data = $storage->latest();
		} else {
			$data = $storage->find($id);
		}

		echo json_encode($data, \JSON_PARTIAL_OUTPUT_ON_ERROR);
	}

	protected function logRequest()
	{
		$this->app->clockwork->resolveRequest();
		$this->app->clockwork->storeRequest();

		if ($this->app->config('debug')) {
			$this->app->response()->header('X-Clockwork-Id', $this->app->clockwork->request()->id);
			$this->app->response()->header('X-Clockwork-Version', Clockwork::VERSION);

			$env = $this->app->environment();
			if ($env['SCRIPT_NAME']) {
				$this->app->response()->header('X-Clockwork-Path', $env['SCRIPT_NAME'] . '/__clockwork/');
			}

			$request = $this->app->clockwork->request();
			$this->app->response()->header('Server-Timing', ServerTiming::fromRequest($request)->value());
		}
	}
}
