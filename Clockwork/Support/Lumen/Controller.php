<?php namespace Clockwork\Support\Lumen;

use Clockwork\Clockwork;
use Clockwork\Support\Lumen\ClockworkSupport;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as LumenController;
use Laravel\Telescope\Telescope;

class Controller extends LumenController
{
	public $clockwork;
	public $clockworkSupport;

	public function __construct(Clockwork $clockwork, ClockworkSupport $clockworkSupport)
	{
		$this->clockwork = $clockwork;
		$this->clockworkSupport = $clockworkSupport;
	}

	public function authenticate(Request $request)
	{
		$this->ensureClockworkIsEnabled();

		$token = $this->clockwork->getAuthenticator()->attempt(
			$request->only([ 'username', 'password' ])
		);

		return new JsonResponse([ 'token' => $token ], $token ? 200 : 403);
	}

	public function getData($id = null, $direction = null, $count = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->clockworkSupport->getData($id, $direction, $count);
	}

	public function getExtendedData($id = null)
	{
		$this->ensureClockworkIsEnabled();

		return $this->clockworkSupport->getExtendedData($id);
	}

	public function webIndex(Request $request)
	{
		$this->ensureClockworkIsEnabled();

		if ($this->clockworkSupport->isWebUsingDarkTheme() && ! $request->exists('dark')) {
			return new RedirectResponse('/__clockwork/app?dark');
		}

		return $this->clockworkSupport->getWebAsset('index.html');
	}

	public function webAsset($path)
	{
		$this->ensureClockworkIsEnabled();

		return $this->clockworkSupport->getWebAsset($path);
	}

	public function webRedirect()
	{
		$this->ensureClockworkIsEnabled();

		return new RedirectResponse('/__clockwork/app');
	}

	protected function ensureClockworkIsEnabled()
	{
		if (class_exists(Telescope::class)) Telescope::stopRecording();

		if (! $this->clockworkSupport->isEnabled()) abort(404);
	}
}
