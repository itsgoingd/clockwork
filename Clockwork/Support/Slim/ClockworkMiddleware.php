<?php
namespace Clockwork\Support\Slim;

use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\SlimDataSource;
use Clockwork\Storage\FileStorage;

use Slim\Middleware;

class ClockworkMiddleware extends Middleware
{
	private $clockwork;
	private $clockworkStoragePath;

	public function __construct($storagePathOrClockwork)
	{
		if ($storagePathOrClockwork instanceof Clockwork) {
			$this->clockwork = $storagePathOrClockwork;
		} else {
			$this->clockworkStoragePath = $storagePathOrClockwork;
		}
	}

	public function call()
	{
		if (!$this->clockwork) {
			$this->clockwork = new Clockwork();

			$this->clockwork->addDataSource(new PhpDataSource())
				->addDataSource(new SlimDataSource($this->app))
				->setStorage(new FileStorage($this->clockworkStoragePath));
		}

		if ($this->app->config('debug')) {
			if (preg_match('#/__clockwork(/(?<id>\d+\.\d+))?#', $this->app->request()->getPathInfo(), $matches))
				return $this->retrieveRequest($matches['id']);

			$this->app->response()->header('X-Clockwork-Version', '0.9.0');
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
		echo $this->clockwork->getStorage()->retrieveAsJson($id, $last);
	}

	protected function logRequest()
	{
		$this->clockwork->resolveRequest();
		$this->clockwork->storeRequest();

		if ($this->app->config('debug')) {
			$this->app->response()->header('X-Clockwork-Id', $this->clockwork->getRequest()->id);
		}
	}
}
