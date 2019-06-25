<?php namespace Clockwork\Support\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Laravel\Telescope\Telescope;

class ClockworkController extends Controller
{
	protected $app;

	public function __construct(Application $app)
	{
		$this->app = $app;
	}

	public function authenticate()
	{
		$this->ensureClockworkIsEnabled();

		$token = $this->app['clockwork']->getAuthenticator()->attempt(
			$this->app['request']->only([ 'username', 'password' ])
		);

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->app['clockwork.support']->getData($id, $direction, $count);
	}

	public function getExtendedData($id = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->app['clockwork.support']->getExtendedData($id);
	}

	public function webIndex()
	{
		$this->ensureClockworkIsEnabled();

		if ($this->app['clockwork.support']->isWebUsingDarkTheme() && ! $this->app['request']->exists('dark')) {
			return new RedirectResponse('/__clockwork/app?dark');
		}

		return $this->app['clockwork.support']->getWebAsset('index.html');
	}

	public function webAsset($path)
	{
		$this->ensureClockworkIsEnabled();

		return $this->app['clockwork.support']->getWebAsset($path);
	}

	public function webRedirect()
	{
		$this->ensureClockworkIsEnabled();

		return new RedirectResponse('/__clockwork/app');
	}

	protected function ensureClockworkIsEnabled()
	{
		if (class_exists(Telescope::class)) Telescope::stopRecording();

		if (! $this->app['clockwork.support']->isEnabled()) abort(404);
	}
}
