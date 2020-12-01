<?php namespace Clockwork\Support\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Laravel\Telescope\Telescope;

// Clockwork api and app controller
class ClockworkController extends Controller
{
	// Laravel app instance
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	// Authantication endpoint
	public function authenticate()
	{
		$this->ensureClockworkIsEnabled();

		$token = $this->app['clockwork']->authenticator()->attempt(
			$this->app['request']->only([ 'username', 'password' ])
		);

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	// Metadata retrieving endpoint
	public function getData($id = null, $direction = null, $count = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->app['clockwork.support']->getData(
			$id, $direction, $count, $this->app['request']->only([ 'only', 'except' ])
		);
	}

	// Extended metadata retrieving endpoint
	public function getExtendedData($id = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->app['clockwork.support']->getExtendedData(
			$id, $this->app['request']->only([ 'only', 'except' ])
		);
	}

	// Metadata updating endpoint
	public function updateData($id = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->app['clockwork.support']->updateData($id, $this->app['request']->json()->all());
	}

	// App index
	public function webIndex()
	{
		$this->ensureClockworkIsEnabled();

		return $this->app['clockwork.support']->getWebAsset('index.html');
	}

	// App assets serving
	public function webAsset($path)
	{
		$this->ensureClockworkIsEnabled();

		return $this->app['clockwork.support']->getWebAsset($path);
	}

	// App redirect (/clockwork -> /clockwork/app)
	public function webRedirect()
	{
		$this->ensureClockworkIsEnabled();

		return new RedirectResponse($this->app['request']->path() . '/app');
	}

	// Ensure Clockwork is still enabled at this point and stop Telescope recording if present
	protected function ensureClockworkIsEnabled()
	{
		if (class_exists(Telescope::class)) Telescope::stopRecording();

		if (! $this->app['clockwork.support']->isEnabled()) abort(404);
	}
}
